<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class BankSubRekening
 */
class BankSubRekening extends EntityModel
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
        return ENTITY_BANK_SUBREKENING;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bankrekening()
    {
        return $this->belongsTo('App\Models\BankRekening');
    }

}

