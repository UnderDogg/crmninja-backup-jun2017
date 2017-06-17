<?php namespace App\Http\Controllers;
// customer
use Utils;
use Response;
use Input;
use App\Models\Customer;
use App\Ninja\Repositories\CustomerRepository;
use App\Http\Requests\CreateCustomerRequest;

class CustomerApiController extends BaseAPIController
{
    protected $customerRepo;

    protected $entityType = ENTITY_CUSTOMER;

    public function __construct(CustomerRepository $customerRepo)
    {
        parent::__construct();

        $this->customerRepo = $customerRepo;
    }

    public function ping()
    {
        $headers = Utils::getApiHeaders();

        return Response::make('', 200, $headers);
    }

    /**
     * @SWG\Get(
     *   path="/customers",
     *   summary="List of customers",
     *   tags={"customer"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with customers",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Customer"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $customers = Customer::scope()
                    ->withTrashed()
                    ->orderBy('created_at', 'desc');

        return $this->listResponse($customers);
    }

    /**
     * @SWG\Post(
     *   path="/customers",
     *   tags={"customer"},
     *   summary="Create a customer",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Customer")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New customer",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Customer"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateCustomerRequest $request)
    {
        $customer = $this->customerRepo->save($request->input());

        $customer = Customer::scope($customer->public_id)
                    ->with('country', 'customer_contacts', 'industry', 'size', 'currency')
                    ->first();

        return $this->itemResponse($customer);
    }
}
