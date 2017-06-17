<?php namespace App\Http\Controllers;

use App\Services\ActivityService;

class ActivityController extends BaseController
{
    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        //parent::__construct();

        $this->activityService = $activityService;
    }

    public function getDatatable($relationPublicId)
    {
        return $this->activityService->getDatatable($relationPublicId);
    }
}
