<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use View;
use URL;
use Input;
use Session;
use Redirect;
use Cache;
use App\Models\Relation;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Task;
use App\Ninja\Repositories\RelationRepository;
use App\Services\RelationService;
use App\Http\Requests\RelationRequest;
use App\Http\Requests\CreateRelationRequest;
use App\Http\Requests\UpdateRelationRequest;

class RelationController extends BaseController
{
    protected $relationService;
    protected $relationRepo;
    protected $entityType = ENTITY_RELATION;

    public function __construct(RelationRepository $relationRepo, RelationService $relationService)
    {
        //parent::__construct();

        $this->relationRepo = $relationRepo;
        $this->relationService = $relationService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', [
            'entityType' => ENTITY_RELATION,
            'title' => trans('texts.relations'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'relation',
              'contact',
              'email',
              'date_created',
              'last_login',
              'balance',
              ''
            ]),
        ]);
    }

    public function getDatatable()
    {
        $search = Input::get('sSearch');
        $userId = Auth::user()->filterId();

        return $this->relationService->getDatatable($search, $userId);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateRelationRequest $request)
    {
        $relation = $this->relationService->save($request->input());

        Session::flash('message', trans('texts.created_relation'));

        return redirect()->to($relation->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show(RelationRequest $request)
    {
        $relation = $request->entity();

        $user = Auth::user();
        Utils::trackViewed($relation->getDisplayName(), ENTITY_RELATION);

        $actionLinks = [];
        if($user->can('create', ENTITY_TASK)){
            $actionLinks[] = ['label' => trans('texts.new_task'), 'url' => URL::to('/tasks/create/'.$relation->public_id)];
        }
        if (Utils::hasFeature(FEATURE_QUOTES) && $user->can('create', ENTITY_INVOICE)) {
            $actionLinks[] = ['label' => trans('texts.new_quote'), 'url' => URL::to('/quotes/create/'.$relation->public_id)];
        }

        if(!empty($actionLinks)){
            $actionLinks[] = \DropdownButton::DIVIDER;
        }

        if($user->can('create', ENTITY_PAYMENT)){
            $actionLinks[] = ['label' => trans('texts.enter_payment'), 'url' => URL::to('/payments/create/'.$relation->public_id)];
        }

        if($user->can('create', ENTITY_CREDIT)){
            $actionLinks[] = ['label' => trans('texts.enter_credit'), 'url' => URL::to('/credits/create/'.$relation->public_id)];
        }

        if($user->can('create', ENTITY_EXPENSE)){
            $actionLinks[] = ['label' => trans('texts.enter_expense'), 'url' => URL::to('/expenses/create/0/'.$relation->public_id)];
        }

        //$token = $relation->getGatewayToken();
        //$customer->getTotalCredit()

        $data = [
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'relation' => $relation,
            'credit' => 0,
            'title' => trans('texts.view_relation'),
            'hasRecurringInvoices' => Invoice::scope()->where('is_recurring', '=', true)->whereCustomerId($relation->id)->count() > 0,
            'hasQuotes' => Invoice::scope()->invoiceType(INVOICE_TYPE_QUOTE)->whereCustomerId($relation->id)->count() > 0,
            'hasTasks' => Task::scope()->whereCustomerId($relation->id)->count() > 0,
            'gatewayLink' => false,
            'gatewayName' => false,
        ];

        return View::make('relations.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(RelationRequest $request)
    {
        if (Relation::scope()->withTrashed()->count() > Auth::user()->getMaxNumRelations()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumRelations().' relations']);
        }

        $data = [
            'relation' => null,
            'method' => 'POST',
            'url' => 'relations',
            'title' => trans('texts.new_relation'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('relations.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function edit(RelationRequest $request)
    {
        $relation = $request->entity();

        $data = [
            'relation' => $relation,
            'method' => 'PUT',
            'url' => 'relations/'.$relation->public_id,
            'title' => trans('texts.edit_relation'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->loginaccount->isNinjaCompany()) {
            if ($company = Company::whereId($relation->public_id)->first()) {
                $data['planDetails'] = $company->getPlanDetails(false, false);
            }
        }

        return View::make('relations.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'company' => Auth::user()->loginaccount,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'currencies' => Cache::get('currencies'),
            'customLabel1' => Auth::user()->loginaccount->custom_relation_label1,
            'customLabel2' => Auth::user()->loginaccount->custom_relation_label2,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateRelationRequest $request)
    {
        $relation = $this->relationService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_relation'));

        return redirect()->to($relation->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->relationService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_relation', $count);
        Session::flash('message', $message);

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('relations/'.Utils::getFirst($ids));
        } else {
            return Redirect::to('relations');
        }
    }
}
