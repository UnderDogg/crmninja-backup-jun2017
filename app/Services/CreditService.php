<?php namespace App\Services;

use Utils;
use Auth;
use App\Ninja\Repositories\CreditRepository;
use App\Ninja\Datatables\CreditDatatable;

/**
 * Class CreditService
 */
class CreditService extends BaseService
{
    /**
     * @var CreditRepository
     */
    protected $creditRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * CreditService constructor.
     *
     * @param CreditRepository $creditRepo
     * @param DatatableService $datatableService
     */
    public function __construct(CreditRepository $creditRepo, DatatableService $datatableService)
    {
        $this->creditRepo = $creditRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return CreditRepository
     */
    protected function getRepo()
    {
        return $this->creditRepo;
    }

    /**
     * @param $data
     * @return mixed|null
     */
    public function save($data)
    {
        return $this->creditRepo->save($data);
    }

    /**
     * @param $relationPublicId
     * @param $search
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($relationPublicId, $search)
    {
        // we don't support bulk edit and hide the relation on the individual relation page
        $datatable = new CreditDatatable(!$relationPublicId, $relationPublicId);
        $query = $this->creditRepo->find($relationPublicId, $search);

        if (!Utils::hasPermission('view_all')) {
            $query->where('credits.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
