<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankRekening
 */
class BankRekening extends EntityModel
{
    use SoftDeletes;
    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_BANK_REKENING;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bank()
    {
        return $this->belongsTo('App\Models\Bank');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bank_subrekeningen()
    {
        return $this->hasMany('App\Models\BankSubRekening');
    }
}

