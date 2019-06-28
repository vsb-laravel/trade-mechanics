<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class PriceArc extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prices_arc';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'U';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'price','instrument_id','source_id','volation'
    ];
}
