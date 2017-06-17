<?php namespace App\Services;

use Auth;
use App\Ninja\Repositories\RelationRepository;
use App\Ninja\Repositories\NinjaRepository;
use App\Ninja\Datatables\RelationDatatable;

/**
 * Class RelationService
 */
class RelationService extends BaseService
{
    /**
     * @var RelationRepository
     */
    protected $relationRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * RelationService constructor.
     * @param RelationRepository $relationRepo
     * @param DatatableService $datatableService
     * @param NinjaRepository $ninjaRepo
     */
    public function __construct(RelationRepository $relationRepo, DatatableService $datatableService, NinjaRepository $ninjaRepo)
    {
        $this->relationRepo = $relationRepo;
        $this->ninjaRepo = $ninjaRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @return RelationRepository
     */
    protected function getRepo()
    {
        return $this->relationRepo;
    }

    /**
     * @param $data
     * @param null $relation
     * @return mixed|null
     */
    public function save($data, $relation = null)
    {
        if (Auth::user()->loginaccount->isNinjaCompany() && isset($data['plan'])) {
            $this->ninjaRepo->updatePlanDetails($data['public_id'], $data);
        }

        return $this->relationRepo->save($data, $relation);
    }

    /**
     * @param $search
     * @param $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($search, $userId)
    {
        $datatable = new RelationDatatable();

        $query = $this->relationRepo->find($search, $userId);

        return $this->datatableService->createDatatable($datatable, $query);
    }
}
