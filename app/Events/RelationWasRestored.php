<?php namespace App\Events;

use App\Models\Relation;
use Illuminate\Queue\SerializesModels;

/**
 * Class RelationWasRestored
 */
class RelationWasRestored extends Event
{
    use SerializesModels;

    /**
     * @var Relation
     */
    public $relation;

    /**
     * Create a new event instance.
     *
     * @param Relation $relation
     */
    public function __construct(Relation $relation)
    {
        $this->relation = $relation;
    }
}
