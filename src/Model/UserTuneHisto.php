<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;


use App\User;
use Vsb\Model\Lead;

class UserTuneHisto extends Model{
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    public $timestamps = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_tune_histo';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'instrument_id','user_id','low','high','open','close','time','volation'
    ];
    public function pair(){
        return $this->belongsTo('Vsb\Crm\Instrument','instrument_id');
    }
    /**
     * Scope a query to only include popular users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByUser($query,User $user,Instrument $pair){
        return $query->where('user_id', $user->id)->where('instrument_id',$pair->id);
    }

}
