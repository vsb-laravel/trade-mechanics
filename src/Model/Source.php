<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sources';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    public $timestamps = 'U';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    const UPDATED_AT = null;
    protected $fillable = [
        'name','url'
    ];
}
