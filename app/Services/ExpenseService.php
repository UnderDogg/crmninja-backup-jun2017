<?php namespace App\Services;

use Auth;
use Utils;
use App\Ninja\Repositories\ExpenseRepository;
use App\Models\Relation;
use App\Models\Vendor;
use App\Models\ExpenseCategory;
use App\Ninja\Datatables\ExpenseDatatable;

/**
 * Class ExpenseService
 */
class ExpenseService extends BaseService
{
    /**
     * @var ExpenseRepository
     */
    protected $expenseRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * ExpenseService constructor.
     *
     * @param ExpenseRepository $expenseRepo
     * @param DatatableService $datatableService
     */
    public function __construct(ExpenseRepository $expenseRepo, DatatableService $datatableService)
    {
        $this->expenseRepo = $expenseRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return ExpenseRepository
     */
    protected function getRepo()
    {
        return $this->expenseRepo;
    }

    /**
     * @param $data
     * @param null $expense
     * @return mixed|null
     */
    public function save($data, $expense = null)
    {
        if (isset($data['relation_id']) && $data['relation_id']) {
            $data['relation_id'] = Relation::getPrivateId($data['relation_id']);
        }

        if (isset($data['vendor_id']) && $data['vendor_id']) {
            $data['vendor_id'] = Vendor::getPrivateId($data['vendor_id']);
        }

        return $this->expenseRepo->save($data, $expense);
    }

    /**
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search)
    {
        $query = $this->expenseRepo->find($search);

        if (!Utils::hasPermission('view_all')) {
            $query->where('expenses.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable(new ExpenseDatatable(), $query);
    }

    /**
     * @param $vendorPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatableVendor($vendorPublicId)
    {
        $datatable = new ExpenseDatatable(false, true);

        $query = $this->expenseRepo->findVendor($vendorPublicId);

        if (!Utils::hasPermission('view_all')) {
            $query->where('expenses.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
