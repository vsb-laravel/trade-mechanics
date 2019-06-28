<?php

namespace Vsb\Model;
use App\User;
use Vsb\Model\Price;
use Vsb\Model\Histo;
use Vsb\Model\InstrumentGroup;
use Vsb\Model\InstrumentGroupPair;
use Illuminate\Database\Eloquent\Model;

class Instrument extends Model{
    // //protected $connection = 'trade_center';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instruments';
    protected $appends = [
        'title',
        'market'
    ];
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
        'from_currency_id','to_currency_id','commission','enabled','multiplex','symbol','type',
        'riskon','low','high','source_id','ordering','grouping',
        'dayswap',
        'spread_buy','spread_sell',
        'lot','pips',
        'price','volation'
    ];
    protected $cast = [
        'low'=>"float",
        'high'=>"float",
        'riskon'=>"int",
        'price'=>'float'
        // 'lot'=>'integer',
        // 'price'=>'array'
    ];

    public function from(){
        return $this->belongsTo('Vsb\Model\Currency','from_currency_id');
    }
    public function to(){
        return $this->belongsTo('Vsb\Model\Currency','to_currency_id');
    }
    public function source(){
        return $this->belongsTo('Vsb\Model\Source');
    }
    public function history(){
        return $this->hasMany('Vsb\Model\InstrumentHistory');
    }
    public function getGroupsAttribute(){
        return InstrumentGroup::whereIn('id',
            InstrumentGroupPair::where('instrument_id',$this->id)->pluck('instrument_group_id')
        )->get();
        // return $ret;
    }
    public function GroupData(User $user,$field){
        $ig = InstrumentGroup::find($user->pairgroup);
        $this->attributes[$field]=$ig->$field;
        return $this->attributes[$field];
    }
    public function getMarketAttribute(){
        return $this->type;
    }
    public function getTitleAttribute(){
        if(is_null($this))return "";
        $this->load(['to','from']);
        $code2 = $this->to();
        $ret = $this->attributes['title']=$this->from->code.(($this->multiplex>1)?'x'.$this->multiplex:'').'/'.$this->to->code;
        // $ret .= " ".$this->source->name;
        return $ret;
    }
    // public function getPriceolAttribute(){
    //     return [];
    //     // $h = Histo::where('instrument_id',$this->id)->orderBy('time','desc')->first();
    //     $pr = Price::where('instrument_id',$this->id)->where('source_id',$this->source_id)->orderBy('time','desc')->limit(2)->get()->toArray();
    //     if(is_null($pr))return [];
    //     try{
    //         $ret = [
    //             "created_at"=>$this->created_at,
    //             "updated_at"=>$this->updated_at,
    //             "diff"=>floatval($pr[0]["price"])-floatval($pr[1]["price"]),
    //             "id"=>$h->id,
    //             "instrument_id"=>$this->id,
    //             "price"=>$pr[0]["price"],
    //             "source_id"=>$this->source_id
    //         ];
    //         $ret['volation'] = ($ret['diff']>=0)?1:-1;
    //     }
    //     catch(\Exception $e){
    //         return [];
    //     }
    //
    //     return $ret;
    // }
    public function getHistoAttribute(){
        return Histo::where('instrument_id',$this->id)->orderBy('time','desc')->first();
    }
}
