<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'orders';
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
        'status','currency_id','user_id','price','volume','account_id','type','acceptor_id'
    ];
    /**
     * Scope a query to only include popular users.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUser($query,User $user)
    {
        return $query->where('user_id', $user->id);
    }
    public function user(){
        return $this->belongsTo('Vsb\Crm\User');
    }
    public function currency(){
        return $this->belongsTo('Vsb\Crm\Currency');
    }
    public function account(){
        return $this->belongsTo('Vsb\Crm\Account');
    }
    public function acceptor(){
        return $this->belongsTo('Vsb\Crm\User','acceptor_id');
    }
}
