<?php namespace App\Http\Controllers;

use Auth;
use Utils;
use View;
use URL;
use Input;
use Session;
use Redirect;
use Cache;
use App\Models\Customer;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Credit;
use App\Models\Task;
use App\Ninja\Repositories\CustomerRepository;
use App\Services\CustomerService;
use App\Http\Requests\CustomerRequest;
use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;

class CustomerController extends BaseController
{
    protected $customerService;
    protected $customerRepo;
    protected $entityType = ENTITY_CUSTOMER;

    public function __construct(CustomerRepository $customerRepo, CustomerService $customerService)
    {
        //parent::__construct();

        $this->customerRepo = $customerRepo;
        $this->customerService = $customerService;
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
            'title' => trans('texts.customers'),
            'sortCol' => '4',
            'columns' => Utils::trans([
              'checkbox',
              'customer',
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

        return $this->customerService->getDatatable($search, $userId);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store(CreateCustomerRequest $request)
    {
        $customer = $this->customerService->save($request->input());

        Session::flash('message', trans('texts.created_customer'));

        return redirect()->to($customer->getRoute());
    }

    /**
     * Display the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function show(CustomerRequest $request)
    {
        $customer = $request->entity();

        $user = Auth::user();
        Utils::trackViewed($customer->getDisplayName(), ENTITY_RELATION);

        $actionLinks = [];
        if($user->can('create', ENTITY_TASK)){
            $actionLinks[] = ['label' => trans('texts.new_task'), 'url' => URL::to('/tasks/create/'.$customer->public_id)];
        }
        if (Utils::hasFeature(FEATURE_QUOTES) && $user->can('create', ENTITY_INVOICE)) {
            $actionLinks[] = ['label' => trans('texts.new_quote'), 'url' => URL::to('/quotes/create/'.$customer->public_id)];
        }

        if(!empty($actionLinks)){
            $actionLinks[] = \DropdownButton::DIVIDER;
        }

        if($user->can('create', ENTITY_PAYMENT)){
            $actionLinks[] = ['label' => trans('texts.enter_payment'), 'url' => URL::to('/payments/create/'.$customer->public_id)];
        }

        if($user->can('create', ENTITY_CREDIT)){
            $actionLinks[] = ['label' => trans('texts.enter_credit'), 'url' => URL::to('/credits/create/'.$customer->public_id)];
        }

        if($user->can('create', ENTITY_EXPENSE)){
            $actionLinks[] = ['label' => trans('texts.enter_expense'), 'url' => URL::to('/expenses/create/0/'.$customer->public_id)];
        }

        $token = $customer->getGatewayToken();

        $data = [
            'actionLinks' => $actionLinks,
            'showBreadcrumbs' => false,
            'customer' => $customer,
            'credit' => $customer->getTotalCredit(),
            'title' => trans('texts.view_customer'),
            'hasRecurringInvoices' => Invoice::scope()->where('is_recurring', '=', true)->whereCustomerId($customer->id)->count() > 0,
            'hasQuotes' => Invoice::scope()->invoiceType(INVOICE_TYPE_QUOTE)->whereCustomerId($customer->id)->count() > 0,
            'hasTasks' => Task::scope()->whereCustomerId($customer->id)->count() > 0,
            'gatewayLink' => $token ? $token->gatewayLink() : false,
            'gatewayName' => $token ? $token->gatewayName() : false,
        ];

        return View::make('customers.show', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create(CustomerRequest $request)
    {
        if (Customer::scope()->withTrashed()->count() > Auth::user()->getMaxNumCustomers()) {
            return View::make('error', ['hideHeader' => true, 'error' => "Sorry, you've exceeded the limit of ".Auth::user()->getMaxNumCustomers().' customers']);
        }

        $data = [
            'customer' => null,
            'method' => 'POST',
            'url' => 'customers',
            'title' => trans('texts.new_customer'),
        ];

        $data = array_merge($data, self::getViewModel());

        return View::make('customers.edit', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int      $id
     * @return Response
     */
    public function edit(CustomerRequest $request)
    {
        $customer = $request->entity();

        $data = [
            'customer' => $customer,
            'method' => 'PUT',
            'url' => 'customers/'.$customer->public_id,
            'title' => trans('texts.edit_customer'),
        ];

        $data = array_merge($data, self::getViewModel());

        if (Auth::user()->loginaccount->isNinjaCompany()) {
            if ($company = Company::whereId($customer->public_id)->first()) {
                $data['planDetails'] = $company->getPlanDetails(false, false);
            }
        }

        return View::make('customers.edit', $data);
    }

    private static function getViewModel()
    {
        return [
            'data' => Input::old('data'),
            'company' => Auth::user()->loginaccount,
            'sizes' => Cache::get('sizes'),
            'paymentTerms' => Cache::get('paymentTerms'),
            'currencies' => Cache::get('currencies'),
            'customLabel1' => Auth::user()->loginaccount->custom_customer_label1,
            'customLabel2' => Auth::user()->loginaccount->custom_customer_label2,
        ];
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int      $id
     * @return Response
     */
    public function update(UpdateCustomerRequest $request)
    {
        $customer = $this->customerService->save($request->input(), $request->entity());

        Session::flash('message', trans('texts.updated_customer'));

        return redirect()->to($customer->getRoute());
    }

    public function bulk()
    {
        $action = Input::get('action');
        $ids = Input::get('public_id') ? Input::get('public_id') : Input::get('ids');
        $count = $this->customerService->bulk($ids, $action);

        $message = Utils::pluralize($action.'d_customer', $count);
        Session::flash('message', $message);

        if ($action == 'restore' && $count == 1) {
            return Redirect::to('customers/'.Utils::getFirst($ids));
        } else {
            return Redirect::to('customers');
        }
    }
}
