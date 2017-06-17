<?php namespace App\Http\Controllers;

use Auth;
use View;
use URL;
use Utils;
use Input;
use Redirect;
use Session;
use DropdownButton;
use App\Models\Relation;
use App\Models\Task;
use App\Ninja\Repositories\TaskRepository;
use App\Ninja\Repositories\InvoiceRepository;
use App\Services\TaskService;
use App\Http\Requests\TaskRequest;
use App\Http\Requests\CreateTaskRequest;
use App\Http\Requests\UpdateTaskRequest;

/**
 * Class TaskController
 */
class TaskController extends BaseController
{
    /**
     * @var TaskRepository
     */
    protected $taskRepo;

    /**
     * @var TaskService
     */
    protected $taskService;

    /**
     * @var
     */
    protected $entityType = ENTITY_TASK;

    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * TaskController constructor.
     * @param TaskRepository $taskRepo
     * @param InvoiceRepository $invoiceRepo
     * @param TaskService $taskService
     */
    public function __construct(
        TaskRepository $taskRepo,
        InvoiceRepository $invoiceRepo,
        TaskService $taskService
    )
    {
        // parent::__construct();

        $this->taskRepo = $taskRepo;
        $this->invoiceRepo = $invoiceRepo;
        $this->taskService = $taskService;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        return View::make('list', [
            'entityType' => ENTITY_TASK,
            'title' => trans('texts.tasks'),
            'sortCol' => '2',
            'columns' => Utils::trans([
              'checkbox',
              'relation',
              'date',
              'duration',
              'description',
              'status',
              ''
            ]),
        ]);
    }

    /**
     * @param null $relationPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($relationPublicId = null)
    {
        return $this->taskService->getDatatable($relationPublicId, Input::get('sSearch'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateTaskRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(CreateTaskRequest $request)
    {
        return $this->save();
    }

    /**
     * @param $publicId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("tasks/{$publicId}/edit");
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param TaskRequest $request
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function create(TaskRequest $request)
    {
        $this->checkTimezone();

        $data = [
            'task' => null,
            'relationPublicId' => Input::old('relation') ? Input::old('relation') : ($request->relation_id ?: 0),
            'method' => 'POST',
            'url' => 'tasks',
            'title' => trans('texts.new_task'),
            'timezone' => Auth::user()->loginaccount->timezone ? Auth::user()->loginaccount->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->loginaccount->getMomentDateTimeFormat(),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('tasks.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param TaskRequest $request
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function edit(TaskRequest $request)
    {
        $this->checkTimezone();

        $task = $request->entity();

        $actions = [];
        if ($task->invoice) {
            $actions[] = ['url' => URL::to("invoices/{$task->invoice->public_id}/edit"), 'label' => trans('texts.view_invoice')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans('texts.invoice_task')];

            // check for any open invoices
            $invoices = $task->relation_id ? $this->invoiceRepo->findOpenInvoices($task->relation_id) : [];

            foreach ($invoices as $invoice) {
                $actions[] = ['url' => 'javascript:submitAction("add_to_invoice", '.$invoice->public_id.')', 'label' => trans('texts.add_to_invoice', ['invoice' => $invoice->invoice_number])];
            }
        }

        $actions[] = DropdownButton::DIVIDER;
        if (!$task->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_task')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_task')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_task')];
        }

        $data = [
            'task' => $task,
            'relationPublicId' => $task->relation ? $task->relation->public_id : 0,
            'method' => 'PUT',
            'url' => 'tasks/'.$task->public_id,
            'title' => trans('texts.edit_task'),
            'duration' => $task->is_running ? $task->getCurrentDuration() : $task->getDuration(),
            'actions' => $actions,
            'timezone' => Auth::user()->loginaccount->timezone ? Auth::user()->loginaccount->timezone->name : DEFAULT_TIMEZONE,
            'datetimeFormat' => Auth::user()->loginaccount->getMomentDateTimeFormat(),
            //'entityStatus' => $task->present()->status,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('tasks.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTaskRequest $request
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateTaskRequest $request)
    {
        $task = $request->entity();

        return $this->save($task->public_id);
    }

    /**
     * @return array
     */
    private static function getViewModel()
    {
        return [
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(),
            'company' => Auth::user()->loginaccount,
        ];
    }

    /**
     * @param null $publicId
     * @return \Illuminate\Http\RedirectResponse
     */
    private function save($publicId = null)
    {
        $action = Input::get('action');

        if (in_array($action, ['archive', 'delete', 'restore'])) {
            return self::bulk();
        }

        $task = $this->taskRepo->save($publicId, Input::all());

        if($publicId) {
            Session::flash('message', trans('texts.updated_task'));
        } else {
            Session::flash('message', trans('texts.created_task'));
        }

        if (in_array($action, ['invoice', 'add_to_invoice'])) {
            return self::bulk();
        }

        return Redirect::to("tasks/{$task->public_id}/edit");
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ?: (Input::get('id') ?: Input::get('ids'));

        if ($action == 'stop') {
            $this->taskRepo->save($ids, ['action' => $action]);
            Session::flash('message', trans('texts.stopped_task'));
            return Redirect::to('tasks');
        } else if ($action == 'invoice' || $action == 'add_to_invoice') {
            $tasks = Task::scope($ids)->with('relation')->get();
            $relationPublicId = false;
            $data = [];

            foreach ($tasks as $task) {
                if ($task->relation) {
                    if (!$relationPublicId) {
                        $relationPublicId = $task->relation->public_id;
                    } else if ($relationPublicId != $task->relation->public_id) {
                        Session::flash('error', trans('texts.task_error_multiple_relations'));
                        return Redirect::to('tasks');
                    }
                }

                if ($task->is_running) {
                    Session::flash('error', trans('texts.task_error_running'));
                    return Redirect::to('tasks');
                } else if ($task->invoice_id) {
                    Session::flash('error', trans('texts.task_error_invoiced'));
                    return Redirect::to('tasks');
                }

                $company = Auth::user()->loginaccount;
                $data[] = [
                    'publicId' => $task->public_id,
                    'description' => $task->description . "\n\n" . $task->present()->times($company),
                    'duration' => $task->getHours(),
                ];
            }

            if ($action == 'invoice') {
                return Redirect::to("invoices/create/{$relationPublicId}")->with('tasks', $data);
            } else {
                $invoiceId = Input::get('invoice_id');
                return Redirect::to("invoices/{$invoiceId}/edit")->with('tasks', $data);
            }
        } else {
            $count = $this->taskRepo->bulk($ids, $action);

            $message = Utils::pluralize($action.'d_task', $count);
            Session::flash('message', $message);

            if ($action == 'restore' && $count == 1) {
                return Redirect::to('tasks/'.$ids[0].'/edit');
            } else {
                return Redirect::to('tasks');
            }
        }
    }

    private function checkTimezone()
    {
        if (!Auth::user()->loginaccount->timezone) {
            $link = link_to('/settings/localization?focus=timezone_id', trans('texts.click_here'), ['target' => '_blank']);
            Session::flash('warning', trans('texts.timezone_unset', ['link' => $link]));
        }
    }
}
