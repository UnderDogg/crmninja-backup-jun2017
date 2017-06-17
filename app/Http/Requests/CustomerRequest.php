<?php namespace App\Http\Requests;

class CustomerRequest extends EntityRequest {

    protected $entityType = ENTITY_CUSTOMER;

    public function entity()
    {
        $customer = parent::entity();
        
        // eager load the contacts
        if ($customer && ! $customer->customerLoaded('contacts')) {
            $customer->load('contacts');
        }
         
        return $customer;
    }
}