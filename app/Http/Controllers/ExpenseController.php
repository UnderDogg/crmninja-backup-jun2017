<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use View;
use URL;
use Input;
use Session;
use Redirect;
use Cache;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Relation;
use App\Models\TaxRate;
use App\Services\ExpenseService;
use App\Ninja\Repositories\ExpenseRepository;
use App\Http\Requests\ExpenseRequest;
use App\Http\Requests\CreateExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;

class ExpenseController extends BaseController
{
    // Expenses
    protected $expenseRepo;
    protected $expenseService;
    protected $entityType = ENTITY_EXPENSE;

    public function __construct(ExpenseRepository $expenseRepo, ExpenseService $expenseService)
    {
        // parent::__construct();

        $this->expenseRepo = $expenseRepo;
        $this->expenseService = $expenseService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return View::make('list', [
            'entityType' => ENTITY_EXPENSE,
            'title' => trans('texts.expenses'),
            'sortCol' => '3',
            'columns' => Utils::trans([
              'checkbox',
              'vendor',
              'relation',
              'expense_date',
              'amount',
              'category',
              'public_notes',
              'status',
              ''
            ]),
        ]);
    }

    public function getDatatable($expensePublicId = null)
    {
        return $this->expenseService->getDatatable(Input::get('sSearch'));
    }

    public function getDatatableVendor($vendorPublicId = null)
    {
        return $this->expenseService->getDatatableVendor($vendorPublicId);
    }

    public function create(ExpenseRequest $request)
    {
        if ($request->vendor_id != 0) {
            $vendor = Vendor::scope($request->vendor_id)->with('vendor_contacts')->firstOrFail();
        } else {
            $vendor = null;
        }

        $data = [
            'vendorPublicId' => Input::old('vendor') ? Input::old('vendor') : $request->vendor_id,
            'expense' => null,
            'method' => 'POST',
            'url' => 'expenses',
            'title' => trans('texts.new_expense'),
            'vendors' => Vendor::scope()->with('vendor_contacts')->orderBy('name')->get(),
            'vendor' => $vendor,
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(),
            'relationPublicId' => $request->relation_id,
            'categoryPublicId' => $request->category_id,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    public function edit(ExpenseRequest $request)
    {
        $expense = $request->entity();

        $expense->expense_date = Utils::fromSqlDate($expense->expense_date);

        $actions = [];
        if ($expense->invoice) {
            $actions[] = ['url' => URL::to("invoices/{$expense->invoice->public_id}/edit"), 'label' => trans('texts.view_invoice')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("invoice")', 'label' => trans('texts.invoice_expense')];
        }

        $actions[] = \DropdownButton::DIVIDER;
        if (!$expense->trashed()) {
            $actions[] = ['url' => 'javascript:submitAction("archive")', 'label' => trans('texts.archive_expense')];
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans('texts.delete_expense')];
        } else {
            $actions[] = ['url' => 'javascript:submitAction("restore")', 'label' => trans('texts.restore_expense')];
        }

        $data = [
            'vendor' => null,
            'expense' => $expense,
            'method' => 'PUT',
            'url' => 'expenses/'.$expense->public_id,
            'title' => 'Edit Expense',
            'actions' => $actions,
            'vendors' => Vendor::scope()->with('vendor_contacts')->orderBy('name')->get(),
            'vendorPublicId' => $expense->vendor ? $expense->vendor->public_id : null,
            'relations' => Relation::scope()->with('contacts')->orderBy('name')->get(),
            'relationPublicId' => $expense->relation ? $expense->relation->public_id : null,
            'categoryPublicId' => $expense->expense_category ? $expense->expense_category->public_id : null,
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('expenses.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateExpenseRequest $request)
    {
        $data = $request->input();
        $data['documents'] = $request->file('documents');

        $expense = $this->expenseService->save($data, $request->entity());

        Session::flash('message', trans('texts.updated_expense'));

        $action = Input::get('action');
        if (in_array($action, ['archive', 'delete', 'restore', 'invoice'])) {
            return self::bulk();
        }

        return redirect()->to("expenses/{$expense->public_id}/edit");
    }

    public function store(CreateExpenseRequest $request)
    {
        $data = $request->input();
        $data['documents'] = $request->file('documents');

        $expense = $this->expenseService->save($data);

        Session::flash('message', trans('texts.created_expense'));

        return redirect()->to("expenses/{$expense->public_id}/edit");
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids    = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');

        switch($action)
        {
            case 'invoice':
                $expenses = Expense::scope($ids)->with('relation')->get();
                $relationPublicId = null;
                $currencyId = null;

                // Validate that either all expenses do not have a relation or if there is a relation, it is the same relation
                foreach ($expenses as $expense)
                {
                    if ($expense->relation) {
                        if (!$relationPublicId) {
                            $relationPublicId = $expense->relation->public_id;
                        } elseif ($relationPublicId != $expense->relation->public_id) {
                            Session::flash('error', trans('texts.expense_error_multiple_relations'));
                            return Redirect::to('expenses');
                        }
                    }

                    if (!$currencyId) {
                        $currencyId = $expense->invoice_currency_id;
                    } elseif ($currencyId != $expense->invoice_currency_id && $expense->invoice_currency_id) {
                        Session::flash('error', trans('texts.expense_error_multiple_currencies'));
                        return Redirect::to('expenses');
                    }

                    if ($expense->invoice_id) {
                        Session::flash('error', trans('texts.expense_error_invoiced'));
                        return Redirect::to('expenses');
                    }
                }

                return Redirect::to("invoices/create/{$relationPublicId}")
                        ->with('expenseCurrencyId', $currencyId)
                        ->with('expenses', $ids);
                break;

            default:
                $count  = $this->expenseService->bulk($ids, $action);
        }

        if ($count > 0) {
            $message = Utils::pluralize($action.'d_expense', $count);
            Session::flash('message', $message);
        }

        return Redirect::to('expenses');
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'company' => Auth::user()->loginaccount,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'industries' => Cache::get('industries'),
            'currencies' => Cache::get('currencies'),
            'languages' => Cache::get('languages'),
            'countries' => Cache::get('countries'),
            'customLabel1' => Auth::user()->loginaccount->custom_vendor_label1,
            'customLabel2' => Auth::user()->loginaccount->custom_vendor_label2,
            'categories' => ExpenseCategory::whereCompanyId(Auth::user()->company_id)->orderBy('name')->get(),
            'taxRates' => TaxRate::scope()->orderBy('name')->get(),
        ];
    }

    public function show($publicId)
    {
        Session::reflash();

        return Redirect::to("expenses/{$publicId}/edit");
    }
}
