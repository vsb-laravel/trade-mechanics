<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class InstrumentGroupPair extends Model{
    //protected $connection = 'trade_center';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instrument_group_pairs';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    public $timestamps = false;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'instrument_group_id','instrument_id'
    ];
    protected $cast = [
        'instrument_group_id'=>'integer',
        'instrument_id'=>'integer',
    ];
    public function group(){
        return $this->belongsTo('Vsb\Crm\InstrumentGroup','instrument_group_id');
    }
    public function pair(){
        return $this->belongsTo('Vsb\Crm\Instrument','instrument_id');
    }

}
