<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class UserTune extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_tune';
    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    public $timestamps = false;
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'coef' => 'float',
    ];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'coef','user_id','time','instrument_id'
    ];
    public function user(){
        return $this->belongsTo('Vsb\Crm\User');
    }
    public function instrument(){
        return $this->belongsTo('Vsb\Crm\Instrument');
    }
}
