<?php namespace App\Ninja\Repositories;

use DB;
use Cache;
use App\Models\Customer;
use App\Models\Contact;
use App\Events\CustomerWasCreated;
use App\Events\CustomerWasUpdated;

class CustomerRepository extends BaseRepository
{
    public function getClassName()
    {
        return 'App\Models\Customer';
    }

    public function all()
    {
        return Customer::scope()
                ->with('user', 'contacts', 'country')
                ->withTrashed()
                ->where('is_deleted', '=', false)
                ->get();
    }

    public function find($filter = null, $userId = false)
    {
        $query = DB::table('customers')
                    ->join('companies', 'companies.id', '=', 'customers.company_id')
                    ->join('contacts', 'contacts.customer_id', '=', 'customers.id')
                    ->where('customers.company_id', '=', \Auth::user()->company_id)
                    ->where('contacts.is_primary', '=', true)
                    ->where('contacts.deleted_at', '=', null)
                    ->select(
                        DB::raw('COALESCE(customers.currency_id, companies.currency_id) currency_id'),
                        DB::raw('COALESCE(customers.country_id, companies.country_id) country_id'),
                        'customers.public_id',
                        'customers.name',
                        'contacts.first_name',
                        'contacts.last_name',
                        'customers.balance',
                        'customers.last_login',
                        'customers.created_at',
                        'customers.work_phone',
                        'contacts.email',
                        'customers.deleted_at',
                        'customers.is_deleted',
                        'customers.user_id'
                    );

        if (!\Session::get('show_trash:customer')) {
            $query->where('customers.deleted_at', '=', null);
        }

        if ($filter) {
            $query->where(function ($query) use ($filter) {
                $query->where('customers.name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.first_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.last_name', 'like', '%'.$filter.'%')
                      ->orWhere('contacts.email', 'like', '%'.$filter.'%');
            });
        }

        if ($userId) {
            $query->where('customers.user_id', '=', $userId);
        }

        return $query;
    }

    public function save($data, $customer = null)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($customer) {
           // do nothing
        } elseif (!$publicId || $publicId == '-1') {
            $customer = Customer::createNew();
        } else {
            $customer = Customer::scope($publicId)->with('contacts')->firstOrFail();
            \Log::warning('Entity not set in customer repo save');
        }

        // convert currency code to id
        if (isset($data['currency_code'])) {
            $currencyCode = strtolower($data['currency_code']);
            $currency = Cache::get('currencies')->filter(function($item) use ($currencyCode) {
                return strtolower($item->code) == $currencyCode;
            })->first();
            if ($currency) {
                $data['currency_id'] = $currency->id;
            }
        }

        $customer->fill($data);
        $customer->save();

        /*
        if ( ! isset($data['contact']) && ! isset($data['contacts'])) {
            return $customer;
        }
        */

        $first = true;
        $contacts = isset($data['contact']) ? [$data['contact']] : $data['contacts'];
        $contactIds = [];

        // If the primary is set ensure it's listed first
        usort($contacts, function ($left, $right) {
            return (isset($right['is_primary']) ? $right['is_primary'] : 1) - (isset($left['is_primary']) ? $left['is_primary'] : 0);
        });

        foreach ($contacts as $contact) {
            $contact = $customer->addContact($contact, $first);
            $contactIds[] = $contact->public_id;
            $first = false;
        }

        if ( ! $customer->wasRecentlyCreated) {
            foreach ($customer->contacts as $contact) {
                if (!in_array($contact->public_id, $contactIds)) {
                    $contact->delete();
                }
            }
        }

        if (!$publicId || $publicId == '-1') {
            event(new CustomerWasCreated($customer));
        } else {
            event(new CustomerWasUpdated($customer));
        }

        return $customer;
    }

    public function findPhonetically($customerName)
    {
        $customerNameMeta = metaphone($customerName);

        $map = [];
        $max = SIMILAR_MIN_THRESHOLD;
        $customerId = 0;

        $customers = Customer::scope()->get(['id', 'name', 'public_id']);

        foreach ($customers as $customer) {
            if ( ! $customer->name) {
                continue;
            }

            $map[$customer->id] = $customer;
            $similar = similar_text($customerNameMeta, metaphone($customer->name), $percent);

            if ($percent > $max) {
                $customerId = $customer->id;
                $max = $percent;
            }
        }

        $contacts = Contact::scope()->get(['customer_id', 'first_name', 'last_name', 'public_id']);

        foreach ($contacts as $contact) {
            if ( ! $contact->getFullName() || ! isset($map[$contact->customer_id])) {
                continue;
            }

            $similar = similar_text($customerNameMeta, metaphone($contact->getFullName()), $percent);

            if ($percent > $max) {
                $customerId = $contact->customer_id;
                $max = $percent;
            }
        }

        return ($customerId && isset($map[$customerId])) ? $map[$customerId] : null;
    }

}
