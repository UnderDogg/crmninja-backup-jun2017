<?php namespace App\Models;

use Utils;
use DB;
use App\Events\CustomerWasCreated;
use App\Events\CustomerWasUpdated;
use App\Events\CustomerWasDeleted;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Customer
 */
class Customer extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\CustomerPresenter';
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * @var array
     */
    protected $fillable = [
        'name',
        'id_number',
        'vat_number',
        'work_phone',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'private_notes',
        'currency_id',
        'website',
        'transaction_name',
    ];

    /**
     * @var string
     */
    public static $fieldName = 'name';
    /**
     * @var string
     */
    public static $fieldPhone = 'work_phone';
    /**
     * @var string
     */
    public static $fieldAddress1 = 'address1';
    /**
     * @var string
     */
    public static $fieldAddress2 = 'address2';
    /**
     * @var string
     */
    public static $fieldCity = 'city';
    /**
     * @var string
     */
    public static $fieldState = 'state';
    /**
     * @var string
     */
    public static $fieldPostalCode = 'postal_code';
    /**
     * @var string
     */
    public static $fieldNotes = 'notes';
    /**
     * @var string
     */
    public static $fieldCountry = 'country';

    /**
     * @return array
     */
    public static function getImportColumns()
    {
        return [
            Customer::$fieldName,
            Customer::$fieldPhone,
            Customer::$fieldAddress1,
            Customer::$fieldAddress2,
            Customer::$fieldCity,
            Customer::$fieldState,
            Customer::$fieldPostalCode,
            Customer::$fieldCountry,
            Customer::$fieldNotes,
            CustomerContact::$fieldFirstName,
            CustomerContact::$fieldLastName,
            CustomerContact::$fieldPhone,
            CustomerContact::$fieldEmail,
        ];
    }

    /**
     * @return array
     */
    public static function getImportMap()
    {
        return [
            'first' => 'first_name',
            'last' => 'last_name',
            'email' => 'email',
            'mobile|phone' => 'phone',
            'name|organization' => 'name',
            'street2|address2' => 'address2',
            'street|address|address1' => 'address1',
            'city' => 'city',
            'state|province' => 'state',
            'zip|postal|code' => 'postal_code',
            'country' => 'country',
            'note' => 'notes',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function loginaccount()
    {
        return $this->belongsTo('App\Models\Company');
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Models\Payment');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function customer_contacts()
    {
        return $this->hasMany('App\Models\CustomerContact');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('App\Models\Country');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currency()
    {
        return $this->belongsTo('App\Models\Currency');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function language()
    {
        return $this->belongsTo('App\Models\Language');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function size()
    {
        return $this->belongsTo('App\Models\Size');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo('App\Models\Industry');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function expenses()
    {
        return $this->hasMany('App\Models\Expense', 'customer_id', 'id');
    }

    /**
     * @param $data
     * @param bool $isPrimary
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function addCustomerContact($data, $isPrimary = false)
    {
        $publicId = isset($data['public_id']) ? $data['public_id'] : false;

        if ($publicId && $publicId != '-1') {
            $contact = CustomerContact::scope($publicId)->firstOrFail();
        } else {
            $contact = CustomerContact::createNew();
        }

        $contact->fill($data);
        $contact->is_primary = $isPrimary;

        return $this->customer_contacts()->save($contact);
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/customers/{$this->public_id}";
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getDisplayName()
    {
        return $this->getName();
    }

    /**
     * @return string
     */
    public function getCityState()
    {
        $swap = $this->country && $this->country->swap_postal_code;
        return Utils::cityStateZip($this->city, $this->state, $this->postal_code, $swap);
    }

    /**
     * @return string
     */
    public function getEntityType()
    {
        return 'customer';
    }

    /**
     * @return bool
     */
    public function hasAddress()
    {
        $fields = [
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country_id',
        ];

        foreach ($fields as $field) {
            if ($this->$field) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getDateCreated()
    {
        if ($this->created_at == '0000-00-00 00:00:00') {
            return '---';
        } else {
            return $this->created_at->format('m/d/y h:i a');
        }
    }

    /**
     * @return mixed
     */
    public function getCurrencyId()
    {
        if ($this->currency_id) {
            return $this->currency_id;
        }

        if (!$this->loginaccount) {
            $this->load('loginaccount');
        }

        return $this->loginaccount->currency_id ?: DEFAULT_CURRENCY;
    }

    /**
     * @return float|int
     */
    public function getTotalExpense()
    {
        return DB::table('expenses')
            ->where('customer_id', '=', $this->id)
            ->whereNull('deleted_at')
            ->sum('amount');
    }
}

Customer::creating(function ($customer) {
    $customer->setNullValues();
});

Customer::created(function ($customer) {
    event(new CustomerWasCreated($customer));
});

Customer::updating(function ($customer) {
    $customer->setNullValues();
});

Customer::updated(function ($customer) {
    event(new CustomerWasUpdated($customer));
});


Customer::deleting(function ($customer) {
    $customer->setNullValues();
});

Customer::deleted(function ($customer) {
    event(new CustomerWasDeleted($customer));
});
