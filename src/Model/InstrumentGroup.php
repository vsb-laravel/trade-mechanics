<?php

namespace Vsb\Model;
use Log;
use Vsb\Model\Option;
use Vsb\Model\Instrument;
use Vsb\Model\InstrumentGroupPair;
use Illuminate\Database\Eloquent\Model;

class InstrumentGroup extends Model{
    protected $appends = ['pairs','stopout','margincall'];
    // //protected $connection = 'trade_center';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instrument_groups';
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
        'name','commission','dayswap','spread_buy','spread_sell','lot','pips','type','forex_stop_out','forex_margin_call'
    ];
    protected $cast = [
        'lot'=>'integer',
        'stopout'=>'float'
        // 'pairs'=>'array'
    ];
    public function getPairsAttribute(){
        // return this->hasManyThrough('Vsb\Crm\Instrument','Vsb\Crm\InstrumentGroupPair','instrument_group_id','instrument_id');
        $ret = Instrument::with(['from','to','source'])->whereIn('id',InstrumentGroupPair::where('instrument_group_id',$this->id)->pluck('instrument_id'))->get();
        return $ret;
    }
    public function getStopoutAttribute(){
        if( isset($this->attributes['forex_stop_out']) && !is_null($this->attributes['forex_stop_out']) ) return $this->attributes['forex_stop_out'];
        $res = Option::where('name','forex.stopout')->first();
        return is_null($res)?0:$res->value;
    }
    public function setStopoutAttribute($value){
        Log::debug('setting stopout to db '.$value);
        return $this->attributes['forex_stop_out'] = $value;
    }
    public function getMargincallAttribute(){
        if( isset($this->attributes['forex_margin_call']) && !is_null($this->attributes['forex_margin_call']) ) return $this->attributes['forex_margin_call'];
        $res = Option::where('name','forex.margincall')->first();
        return is_null($res)?0:$res->value;
    }
    public function setMargincallAttribute($value){
        return $this->attributes['forex_margin_call'] = $value;
    }
}


//
//  select `instruments`.*, `instrument_group_pairs`.`instrument_group_id`
//  from `instruments`
//     inner join `instrument_group_pairs` on `instrument_group_pairs`.`id` = `instruments`.`instrument_id`
// where `instrument_group_pairs`.`instrument_group_id` in (1, 2, 3)
