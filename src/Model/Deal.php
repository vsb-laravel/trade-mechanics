<?php

namespace Vsb\Model;

use Cookie;
use Illuminate\Database\Eloquent\Model;
use Vsb\Model\DealStatus;
use Vsb\Model\Currency;
use Vsb\Model\Instrument;
use Vsb\Model\Account;
use App\User;


class Deal extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'deals';
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
        'status_id','instrument_id','user_id','open_price','close_price','direction',
        'stop_high','stop_low','amount','currency_id','multiplier',
        'profit','price_start','price_stop','account_id','volation',
        'fee','invested','type',
        'volume','lot','pips'
    ];

    protected $cast = [
        'invested'=>"decimal:2",
        'amount'=>"decimal:2",
        'fee'=>"decimal:2",
        'profit'=>"decimal:2",

        'status_id'=>"integer",
        'instrument_id'=>"integer",
        'user_id'=>"integer",
        'direction'=>"integer",
        'account_id'=>"integer",
        'volation'=>"integer",
        'currency_id'=>"integer",
        'multiplier'=>"integer",

        'open_price'=>"decimal:5",
        'close_price'=>"decimal:5",
        'stop_high'=>"decimal:5",
        'stop_low'=>"decimal:5",
        'price_start'=>"decimal:5",
        'price_stop'=>"decimal:5",
        'volume'=>"decimal:5",
        'lot'=>"decimal:5",
        'pips'=>"decimal:5"
    ];
    public function history(){
        return $this->hasMany('Vsb\Model\DealHistory');
    }
    public function user(){
        return $this->belongsTo('Vsb\Model\User');
    }
    public function account(){
        return $this->belongsTo('Vsb\Model\Account');
    }
    public function status(){
        return $this->belongsTo('Vsb\Model\DealStatus');
    }
    public function currency(){
        return $this->belongsTo('Vsb\Model\Currency');
    }
    public function instrument(){
        return $this->belongsTo('Vsb\Model\Instrument');
    }
    public function isForex(){
        return $this->type==='forex';
    }
    public function scopeByUser($query,$user){
        if(is_null($user) || $user==false) return $query;
        // $acc = Account::where('user_id',$user)->where('type',Cookie::get('cryptofx_mode','demo'))->first();
        // if(is_null($acc) || $acc == false) return $query->where('user_id',$user);
        return $query->where('user_id', '=', $user);
    }
    public function events(){
        return $this->morphMany('Vsb\Model\Event', 'object');
    }
    public function scopeByDemo($query){
        return $query->whereIn('account_id', Account::where('type','demo')->select('id')->get());
    }
    public function scopeByLive($query){
        return $query->whereIn('account_id', Account::where('type','real')->select('id')->get());
    }
    public function scopeByInstrument($query,$str){
        if(false==$str || is_null($str) || "false"==$str) return $query;
        return $query->where('instrument_id', '=', $str);
    }
    public function scopeByStatus($query,$str){
        if($str==false || is_null($str) || $str == "all") return $query;
        $status = DealStatus::where('code','=',$str)->first();
        if($status===false || is_null($status)) return $query;
        return $query->where('status_id', '=', $status->id);
    }
}
/*
ALTER TABLE `deals` ADD `invested` DECIMAL(9,2) UNSIGNED NOT NULL AFTER `fee`;
update deals set invested = amount+fee;
*/
