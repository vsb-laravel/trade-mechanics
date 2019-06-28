<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Histo extends Model
{
    protected $orderBy = 'id';
    protected $orderDirection = 'DESC';
    // protected $connection = 'trade_center';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'histo';
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
        'instrument_id','source_id','exchange','open','close','low','high','volumefrom','volumeto','time'
    ];
    public function pair(){
        return $this->belongsTo('Vsb\Crm\Instrument','instrument_id');
    }
    public function source(){
        return $this->belongsTo('Vsb\Crm\Source','source_id');
    }
}
