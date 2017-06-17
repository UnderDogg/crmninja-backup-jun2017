<?php namespace App\Services;

use App\Models\Customer;
use Utils;
use Auth;
use App\Ninja\Repositories\CustomerRepository;
use App\Ninja\Repositories\NinjaRepository;
use App\Ninja\Datatables\CustomerDatatable;

/**
 * Class CustomerService
 */
class CustomerService extends BaseService
{
    /**
     * @var CustomerRepository
     */
    protected $customerRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CustomerService constructor.
     *
     * @param CustomerRepository $customerRepo
     * @param DatatableService $datatableService
     * @param NinjaRepository $ninjaRepo
     */
    public function __construct(
        CustomerRepository $customerRepo,
        DatatableService $datatableService,
        NinjaRepository $ninjaRepo
    )
    {
        $this->customerRepo = $customerRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CustomerRepository
     */
    protected function getRepo()
    {
        return $this->customerRepo;
    }

    /**
     * @param array $data
     * @param Customer|null $customer
     * @return mixed|null
     */
    public function save(array $data, Customer $customer = null)
    {
        if (Auth::user()->loginaccount->isNinjaCompany() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->customerRepo->save($data, $customer);
    }

    /**
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search)
    {
        $datatable = new CustomerDatatable();
        $query = $this->customerRepo->find($search);

        if (!Utils::hasPermission('view_all')) {
            $query->where('customers.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
