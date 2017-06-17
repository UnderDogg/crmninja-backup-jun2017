<?php namespace App\Ninja\Transformers;

use App\Models\Company;
use App\Models\Task;
use App\Models\Relation;

/**
 * @SWG\Definition(definition="Task", @SWG\Xml(name="Task"))
 */

class TaskTransformer extends EntityTransformer
{
    /**
    * @SWG\Property(property="id", type="integer", example=1, readOnly=true)
    * @SWG\Property(property="amount", type="float", example=10, readOnly=true)
    * @SWG\Property(property="invoice_id", type="integer", example=1)
    */
    protected $availableIncludes = [
        'relation',
    ];


    public function __construct(Company $company)
    {
        parent::__construct($company);

    }

    public function includeRelation(Task $task)
    {
        if ($task->relation) {
            $transformer = new RelationTransformer($this->loginaccount, $this->serializer);
            return $this->includeItem($task->relation, $transformer, 'relation');
        } else {
            return null;
        }
    }

    public function transform(Task $task)
    {
        return array_merge($this->getDefaults($task), [
            'id' => (int) $task->public_id,
            'description' => $task->description,
            'duration' => $task->getDuration()
        ]);
    }
}