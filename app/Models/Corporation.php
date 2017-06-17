<?php namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class Corporation
 */
class Corporation extends Eloquent
{
    use SoftDeletes;

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function companies()
    {
        return $this->hasMany('App\Models\Company');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment()
    {
        return $this->belongsTo('App\Models\Payment');
    }
}
