<?php

namespace Vsb\Model;

use Illuminate\Database\Eloquent\Model;

class Price extends Model
{
    // //protected $connection = 'trade_center';
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'prices';
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
        'price','instrument_id','source_id','volation','time'
    ];
    // public function getTimeAttribute(){
    //     return -intval($this->time);
    // }
    public function pair(){
        return $this->belongsTo('Vsb\Crm\Instrument','instrument_id');
    }
    public function source(){
        return $this->belongsTo('Vsb\Crm\Source');
    }
}
/*
alter table prices add time int(10) null;
update `prices` set time=created_at;
ALTER TABLE `prices` CHANGE `time` `time` INT(10) UNSIGNED NOT NULL;
drop table temp_price;
create table temp_price select time,instrument_id,max(id)as id from prices group by time,instrument_id having count(*) > 1;
delete from prices where id in (select id from temp_price);
select time,instrument_id,max(id)as id,count(*) from prices group by time,instrument_id having count(*) > 1;
*/
