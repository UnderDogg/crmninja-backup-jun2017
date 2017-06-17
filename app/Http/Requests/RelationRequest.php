<?php namespace App\Http\Requests;

class RelationRequest extends EntityRequest {

    protected $entityType = ENTITY_RELATION;

    public function entity()
    {
        $relation = parent::entity();
        
        // eager load the contacts
        if ($relation && ! $relation->relationLoaded('contacts')) {
            $relation->load('contacts');
        }
         
        return $relation;
    }
}