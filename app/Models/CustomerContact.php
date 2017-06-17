<?php namespace App\Models;

// customer

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class CustomerContact
 */
class CustomerContact extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * @var string
     */
    protected $table = 'customer_contacts';

    /**
     * @var array
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'send_invoice',
    ];

    /**
     * @var string
     */
    public static $fieldFirstName = 'first_name';
    /**
     * @var string
     */
    public static $fieldLastName = 'last_name';
    /**
     * @var string
     */
    public static $fieldEmail = 'email';
    /**
     * @var string
     */
    public static $fieldPhone = 'phone';

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
     * @return mixed
     */
    public function customer()
    {
        return $this->belongsTo('App\Models\Customer')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function getPersonType()
    {
        return PERSON_CUSTOMER_CONTACT;
    }

    /**
     * @return mixed|string
     */
    public function getName()
    {
        return $this->getDisplayName();
    }

    /**
     * @return mixed|string
     */
    public function getDisplayName()
    {
        if ($this->getFullName()) {
            return $this->getFullName();
        } else {
            return $this->email;
        }
    }

    /**
     * @return string
     */
    public function getFullName()
    {
        if ($this->first_name || $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        } else {
            return '';
        }
    }
}
