<?php namespace Vsb\Traits;
use App\UserMeta;
use Vsb\Model\InstrumentGroup;
trait UserTrades{
    public function deal(){
        return $this->hasMany('Vsb\Model\Deal')->orderBy('id','desc');
    }
    public function trades(){
        return $this->hasMany('Vsb\Model\Deal')->where('status_id','<','100')->orderBy('id','desc');
    }
    public function orders(){
        return $this->hasMany('Vsb\Model\Order')->orderBy('id','desc');
    }
    public function activedeals(){
        return $this->hasMany('Vsb\Model\Deal')->where('status_id','10');
    }
    // attributes
    public function getPairgroupAttribute(){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','pairgroup')->first();
        if(is_null($ret)){
            $ig = InstrumentGroup::where('name','default')->first();
            $ret = is_null($ig)?1:$ig->id;
        }else $ret = $ret->meta_value;
        return $ret;
    }
    public function getGroupAttribute(){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','pairgroup')->first();
        return (is_null($ret))?InstrumentGroup::where('name','default')->first():InstrumentGroup::find($ret->meta_value);
    }
    public function getMargincallAttribute(){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','margincall')->first();
        return is_null($ret)?0:$ret->meta_value;
    }
    public function setMargincallAttribute($value){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','margincall')->first();
        $ret->meta_value = $value;
        $ret->save();
    }
    public function getStopoutAttribute(){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','stopout')->first();
        if(is_null($ret)){
            $ret = $this->getGroupAttribute()->stopout;
        }else $ret = $ret->meta_value;

        return $ret;
    }
    public function setStopoutAttribute($value){
        $ret = UserMeta::where('user_id',$this->id)->where('meta_name','stopout')->first();
        $ret->meta_value = $value;
        $ret->save();
    }
}
?>
