<?php namespace App\Services;

use App\Ninja\Repositories\AccountGatewayRepository;
use App\Ninja\Datatables\AccountGatewayDatatable;

/**
 * Class AccountGatewayService
 */
class AccountGatewayService extends BaseService
{
    /**
     * @var AccountGatewayRepository
     */
    protected $accountGatewayRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * AccountGatewayService constructor.
     *
     * @param AccountGatewayRepository $accountGatewayRepo
     * @param DatatableService $datatableService
     */
    public function __construct(AccountGatewayRepository $accountGatewayRepo, DatatableService $datatableService)
    {
        $this->accountGatewayRepo = $accountGatewayRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return AccountGatewayRepository
     */
    protected function getRepo()
    {
        return $this->accountGatewayRepo;
    }

    /**
     * @param $companyId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($companyId)
    {
        $query = $this->accountGatewayRepo->find($companyId);

        return $this->datatableService->createDatatable(new AccountGatewayDatatable(false), $query);
    }
}
