<?php namespace App\Services;

use App\Models\Relation;
use App\Ninja\Repositories\ActivityRepository;
use App\Ninja\Datatables\ActivityDatatable;

/**
 * Class ActivityService
 */
class ActivityService extends BaseService
{
    /**
     * @var ActivityRepository
     */
    protected $activityRepo;

    /**
     * @var DatatableService
     */
    protected $datatableService;

    /**
     * ActivityService constructor.
     *
     * @param ActivityRepository $activityRepo
     * @param DatatableService $datatableService
     */
    public function __construct(ActivityRepository $activityRepo, DatatableService $datatableService)
    {
        $this->activityRepo = $activityRepo;
        $this->datatableService = $datatableService;
    }

    /**
     * @param null $relationPublicId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDatatable($relationPublicId = null)
    {
        $relationId = Relation::getPrivateId($relationPublicId);

        $query = $this->activityRepo->findByRelationId($relationId);

        return $this->datatableService->createDatatable(new ActivityDatatable(false), $query);
    }
}
