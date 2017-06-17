<?php namespace App\Ninja\Repositories;

use App\Models\Customer;
use App\Models\CustomerContact;

// customer
class CustomerContactRepository extends BaseRepository
{
    public function save($data)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if (!$publicId || $publicId == '-1') {
            $contact = CustomerContact::createNew();
            //$contact->send_invoice = true;
            $contact->customer_id = $data['customer_id'];
            $contact->is_primary = CustomerContact::scope()->where('customer_id', '=', $contact->customer_id)->count() == 0;
        } else {
            $contact = CustomerContact::scope($publicId)->firstOrFail();
        }

        $contact->fill($data);
        $contact->save();

        return $contact;
    }
}