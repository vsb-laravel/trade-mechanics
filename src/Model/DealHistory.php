<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class DealHistory extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deal_hist';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'U';
    const UPDATED_AT = null;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'deal_id','old_status_id','changed_user_id','new_status_id','description'
    ];
}
