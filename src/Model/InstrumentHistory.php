<?php

namespace Vsb\Model;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class InstrumentHistory extends Authenticatable{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'U';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instrument_hist';
    const UPDATED_AT = null;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'instrument_id','old_enabled','new_enabled','old_commission','new_commission'
    ];
}
