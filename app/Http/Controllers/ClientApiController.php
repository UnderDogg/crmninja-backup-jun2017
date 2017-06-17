<?php namespace App\Http\Controllers;

use Response;
use Input;
use App\Models\Relation;
use App\Ninja\Repositories\RelationRepository;
use App\Http\Requests\CreateRelationRequest;
use App\Http\Requests\UpdateRelationRequest;

class RelationApiController extends BaseAPIController
{
    protected $relationRepo;

    protected $entityType = ENTITY_RELATION;

    public function __construct(RelationRepository $relationRepo)
    {
        parent::__construct();

        $this->relationRepo = $relationRepo;
    }

    /**
     * @SWG\Get(
     *   path="/relations",
     *   summary="List of relations",
     *   tags={"relation"},
     *   @SWG\Response(
     *     response=200,
     *     description="A list with relations",
     *      @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function index()
    {
        $relations = Relation::scope()
            ->orderBy('created_at', 'desc')
            ->withTrashed();

        // Filter by email
        if ($email = Input::get('email')) {
            $relations = $relations->whereHas('contacts', function ($query) use ($email) {
                $query->where('email', $email);
            });
        }

        return $this->listResponse($relations);
    }

    /**
     * @SWG\Post(
     *   path="/relations",
     *   tags={"relation"},
     *   summary="Create a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="New relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */
    public function store(CreateRelationRequest $request)
    {
        $relation = $this->relationRepo->save($request->input());

        return $this->itemResponse($relation);
    }

    /**
     * @SWG\Put(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Update a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Update relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function update(UpdateRelationRequest $request, $publicId)
    {
        if ($request->action) {
            return $this->handleAction($request);
        }

        $data = $request->input();
        $data['public_id'] = $publicId;
        $relation = $this->relationRepo->save($data, $request->entity());

        $relation->load(['contacts']);

        return $this->itemResponse($relation);
    }


    /**
     * @SWG\Delete(
     *   path="/relations/{relation_id}",
     *   tags={"relation"},
     *   summary="Delete a relation",
     *   @SWG\Parameter(
     *     in="body",
     *     name="body",
     *     @SWG\Schema(ref="#/definitions/Relation")
     *   ),
     *   @SWG\Response(
     *     response=200,
     *     description="Delete relation",
     *      @SWG\Schema(type="object", @SWG\Items(ref="#/definitions/Relation"))
     *   ),
     *   @SWG\Response(
     *     response="default",
     *     description="an ""unexpected"" error"
     *   )
     * )
     */

    public function destroy(UpdateRelationRequest $request)
    {
        $relation = $request->entity();

        $this->relationRepo->delete($relation);

        return $this->itemResponse($relation);
    }

}
