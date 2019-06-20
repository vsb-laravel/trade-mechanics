<?php namespace Vsb;
use DB;
use Log;
use App\Histo;
use App\HistoHour;
use App\HistoDay;
use App\Event;
use App\Price;
use App\Deal;
use App\User;
use App\UserTune;
use App\UserTunePrice;
use App\UserTuneHisto;
use App\Events\PriceTuneEvent;
use App\UserMeta;
use App\Instrument;
use Illuminate\Database\Eloquent\ModelNotFoundException;
class DataTune{
    public static $smoothing = 0.0001;
    public static $precision = 100000;
    public static function corrida(User $user,Instrument $pair,$data){
        $ret = null;
        $userMeta = UserMeta::byUser($user)->meta('user_tune_corida_#'.$pair->id)->first();
        if(is_null($userMeta) || $userMeta==false) {
            Log::debug('wrong tune user, no meta');
            return $data;
        }
        // if($data instanceof Price){ // price data

            $ret = UserTunePrice::where('instrument_id',$pair->id)
                ->where('time',$data->time)
                ->where('user_id',$user->id)->first();
            if(!is_null($ret)) return $data;

            $price = floatval($data->price);
            $newValue = floatval($data->price);

            $corridaArr = json_decode($userMeta->meta_value,true);
            if(!is_array($corridaArr)) {
                Log::debug('Corida u<'.$user->id.'> tune config <'.(is_object($corridaArr)?json_encode($corridaArr):$corridaArr).'>');
                return $data;
            }
            if(!isset($corridaArr['gone'])){
                $corridaArr['gone'] = 0;
            }
            $corrida = json_decode(json_encode($corridaArr));
            //if following return
            if(isset($corrida->following) && $corrida->following == "true") return $data;

            $level = floatval($corrida->high);
            // $smoothing = isset($corrida->smoothing)?floatval($corrida->smoothing):self::$smoothing;
            $smoothing = floatval($corrida->low);
            $smoothing = ($smoothing==0)?self::$smoothing:$smoothing;
            if( (floatval($corrida->low)/$level) >0.05) $corrida->low = 0.01;
            $range = $level*floatval($corrida->low);

            $last = UserTunePrice::where('instrument_id',$pair->id)->where('user_id',$user->id)->where('time','>',($data->time - 3*60*60) )->orderBy('time','desc')->first();
            $timeSmooth = intval( is_null($last)?$data->time-1:$last->time );
            $smoothingDemph = (intval($data->time) - $timeSmooth)/60;
            $smoothingDemph = ($smoothingDemph>60)?60:$smoothingDemph;
            $smoothing*=$smoothingDemph;
            // Log::debug('Tune prepeare '.json_encode($corrida));
            if($corrida->riskon==1){
                if( ($price < ($level-$range) || $price > ($level+$range) )){
                    $priceCheck = $price+floatval($corrida->gone);
                    $newValue = self::smooth($priceCheck,$level,$smoothing);//too fast
                    if($newValue == 0 )$newValue = $price*$smoothing;
                    if( abs(($newValue-$price)/$price) > 0.01) $newValue =  self::smooth($price,$level,0.01);//too fast*$price
                    $corrida->gone = $newValue-$price;
                }
                if( ($newValue >= ($level-$range) && $newValue < ($level+$range) )){
                    //reached tuning
                    $description = "Autoclose deal by settings. [Pair:{$pair->id} Source:{$pair->source_id}] Reached tuning level: {$level}";
                    $deal = isset($corrida->deal_id)?Deal::find($corrida->deal_id):null;
                    if(!is_null($deal)){
                        if(isset($corrida->onclose) && $corrida->onclose==1 && $corrida->riskon==1){
                            $corrida->riskon=0;
                            $dealController = new \App\Http\Controllers\DealController();
                            Log::debug("[{$pair->id}:{$pair->source_id}] Autoclose deal #".$deal->id." by settings");
                            if(!is_null($deal))$dealController->closeDeal($user,$deal,$level,$description);
                        }else {
                            if( Event::where('object_id',$deal->id)->where('object_type','deal')->where('user_id',$deal->user_id)->where('type','tuned')->count()==0 ) $deal->events()->create(['type'=>'tuned','user_id'=>$deal->user_id]);
                        }
                    }
                }
            }
            else {
                if(is_null($last))$last = $data;
                // $lastPrice = floatval($last->price);
                $level = $price;
                $priceCheck = floatval($last->price);
                $newValue = self::smooth($priceCheck,$level,floatval($corrida->low));
                if($newValue <= 0 )$newValue =$price*floatval($corrida->low);
                $corrida->gone = $newValue-$level;
                if( ($newValue >= ($level-$range) && $newValue < ($level+$range) )){
                    $userMeta->delete();
                    $usersFollowers = UserMeta::where('meta_name','subscribe_follow_trade#'.$corrida->deal_id)->get();
                    foreach($usersFollowers as $userm){
                        UserMeta::where('user_id',$userm->user_id)->where('meta_name','user_tune_corida_#'.$pair->id)->delete();
                    }
                    return $data;
                }
                //debug tuning
                try{$um_debug = UserMeta::firstOrCreate(['user_id'=>$user->id,'meta_value'=>json_encode(["last"=>$last,"data"=>$data,"level"=>$level,"priceCheck"=>$priceCheck,"newValue"=>$newValue]),'meta_name'=>'_debug_tune_#'.$pair->id]);}catch(\Exception $e){}
            }
            try{
                if( $newValue <= 0 ) {
                    $corrida->gone = $newValue;
                    $newValue += 4*abs($smoothing);
                }

                // $utp = UserTunePrice::firstOrCreate([
                //     'user_id'=>$user->id,
                //     'instrument_id'=>$pair->id,
                //     'time'=>$data->time,
                //     'price'=>$newValue,
                //     'price_id'=>$data->id
                // ]);
                $utp = [
                    'user_id'=>$user->id,
                    'instrument_id'=>$pair->id,
                    'time'=>$data->time,
                    'price'=>$newValue,
                    // 'price_id'=>$data->id
                ];
                event(new PriceTuneEvent($utp));
                $usersFollowers = UserMeta::where('meta_name','subscribe_follow_trade#'.$corrida->deal_id)->get();
                $corridaf = $corridaArr;
                foreach($usersFollowers as $userm){
                        $userMetaF = UserMeta::where('user_id',$userm->user_id)->meta('user_tune_corida_#'.$pair->id)->first();
                        if(is_null($userMetaF)){
                            $ff = json_decode($userm->meta_value);
                            $corridaf["deal_id"] = $ff->deal;
                            $corridaf["following"] = "true";
                            UserMeta::create([
                                'user_id'=>$userm->user_id,
                                'meta_name'=>'user_tune_corida_#'.$pair->id,
                                'meta_value'=>json_encode($corridaf)
                            ]);
                        }
                        $utp['user_id']=$userm->user_id;
                        event(new PriceTuneEvent($utp));
                    }
            }
            catch(\Exception $e){
                Log::error('Tune object:'.$e->getMessage());
            }
            $userMeta->update(['meta_value'=>json_encode($corrida)]);
        // }
        // else Log::debug('not instance of Price');
        return is_null($ret)?$data:$ret;
    }
    public static function smooth($price,$level,$smoothing){
        $ret = $price;
        $ret = $price +(($price>$level)?-1:1) * $price*$smoothing;
        return $ret;
    }
    public static function flocky(User $user,Instrument $pair,$data){
        $ret = $data;
        $um = UserMeta::byUser($user)->meta('user_tune_#'.$pair->id)->first();
        if(is_null($um) || $um == false) return $ret;
        $tune = json_decode($um->meta_value,true);
        $time = time();
        $precision = 10;
        $current = 0;
        $changed = false;
        if(!isset($tune["started"]))$tune["started"]=$time+60;
        if(($time-$tune["started"])>0){
            $current = (isset($tune["current"]))?floatval($tune["current"]):0;
            $diffTime =  $time - (isset($tune["lasted"])?$tune["lasted"]:$time);
            if($diffTime <=0) $current = $tune["current"];
            if( ($time - ($tune["started"]) ) >= $precision*$tune["flying"]) {$current = $tune["coef"];}
            else if(
                    ($tune["coef"]>0 && $current < $tune["coef"]) ||
                    ($tune["coef"]<0 && $current > $tune["coef"])
                ) {$changed=true;$current += $diffTime * ( $tune["coef"]/($precision*$tune["flying"]) );}
            else if(
                    ($tune["coef"]>0 && $current > $tune["coef"]) ||
                    ($tune["coef"]<0 && $current < $tune["coef"])
                ) {$changed=true;$current -= $diffTime * ( $tune["coef"]/($precision*$tune["flying"]) );}
            $tune["current"]=$current;
            $tune["lasted"]=$time;
            if($changed){
                // echo "Changed!\n";
                $userMeta->update(["meta_value"=>json_encode($tune)]);
                if(is_null($userTune) || $userTune->coef !=(1+$current) || $current!=0 ){
                    // echo "Tune chnaged was: ".(is_null($userTune)?'none':$userTune->coef)." now: ".$current."\n";
                    if($data instanceof Price){
                        $ret = UserTunePrice::where('price_id',$data->id)->first();
                        if(is_null($ret)){
                            $ret = UserTunePrice::create([
                                'user_id'=>$user->id,
                                'instrument_id'=>$pair->id,
                                'time'=>strtotime($data->created_at),
                                'price'=>$data->price*(1+$current),
                                'price_id'=>$data->id
                            ]);
                        }
                    }
                }
                else if($data instanceof Histo){
                    $hs = [
                        Histo::where('instrument_id',$pair->id)->where('created_at','>=',strtotime($um->created_at))->whereNotIn('id',UserTuneHisto::byUser($user,$pair)->where('time','>=',strtotime($um->created_at))->where('object_type','minute')->select('object_id')->get()),
                        // HistoHour::where('instrument_id',$pair->id)->where('created_at','>=',strtotime($userMeta->created_at))->whereNotIn('id',UserTuneHisto::byUser($user,$pair)->where('time','>=',strtotime($userMeta->created_at))->where('object_type','hour')->select('object_id')->get())->get(),
                        // HistoDay::where ('instrument_id',$pair->id)->where('created_at','>=',strtotime($userMeta->created_at))->whereNotIn('id',UserTuneHisto::byUser($user,$pair)->where('time','>=',strtotime($userMeta->created_at))->where('object_type','day')->select('object_id')->get())->get()
                    ];
                    foreach($hs as $hms){
                        $lastClose=null;
                        foreach ($hms->get() as $hm) {
                            // echo json_encode($hm)."\n";
                            if(is_null($lastClose)){
                                $lastCandle = UserTuneHisto::byUser($user,$pair)->where('object_type',(($hm instanceof HistoHour)?'hour':(($hm instanceof HistoDay)?'day':'minute')))->where('object_id','<',$hm->id)->orderBy('id','desc')->first();
                                if(is_null($lastCandle)){
                                    $lastCandle = Histo::where('instrument_id',$pair->id)->where('id','<',$hm->id)->orderBy('id','desc')->first();
                                }
                                $lastClose = is_null($lastCandle)?null:$lastCandle->close;
                            }
                            $arr = [
                                'user_id'=>$user->id,
                                'instrument_id'=>$pair->id,
                                'time'=>$hm->time,
                                'object_id'=>$hm->id,
                                'object_type'=> (($hm instanceof HistoHour)?'hour':(($hm instanceof HistoDay)?'day':'minute')),
                                'open'=>is_null($lastClose)?$hm->open:$lastClose,
                                'volation'=>'1'
                            ];
                            $arr['close'] = ($hm->close < $corrida->low || $hm->close > $corrida->high)
                                ?rand(intval($corrida->low*self::$precision),intval($corrida->high*self::$precision))/self::$precision
                                :$hm->close;
                            $arr['volation']=($arr['open']>$arr['close'])?-1:1;
                            if($arr['volation']>0){
                                $arr['low'] = rand(intval($corrida->low*self::$precision),intval($arr['open']*self::$precision))/self::$precision;
                                $arr['high'] = rand(intval($arr['close']*self::$precision),intval($corrida->high*self::$precision))/self::$precision;
                            }else{
                                $arr['low'] = rand(intval($corrida->low*self::$precision),intval($arr['close']*self::$precision))/self::$precision;
                                $arr['high'] = rand(intval($arr['open']*self::$precision),intval($corrida->high*self::$precision))/self::$precision;;
                            }
                            $lastClose = $arr['close'];
                            try{
                                $ret = UserTuneHisto::create($arr);
                            }
                            catch(\Exception $e){}

                        }
                    }
                }
            }

        }
        // echo "Returning ".(1+$current)." (".$current.")\n";
        return $ret;
    }
    public static function fork(User $user){
        $usertune = UserMeta::byUser($user)->meta("user_chart_tune")->first();
        if(is_null($usertune) || $usertune == false) return 1;
        $utdata = UserMeta::byUser($user)->meta("user_chart_tune_data")->first();
        $data = [
            "last"=>time(),
            "from"=>1,
            "to"=>1+(intval($usertune->meta_value)/100),
            "current"=>1,
            "step"=>0.05,
            "done"=>0
        ];
        if(!is_null($utdata) && $utdata !== false){
            $data = json_decode($utdata->meta_value,true);
            if($data["current"]==$data["to"]){
                $data["done"] = 1;
            }
            else if(time()-$data["last"]>1){
                $data["current"]= $data["current"]+((($data["to"]-$data["from"])<0)?-1:1)*$data["step"];
                $data["last"] = time();
            }
        }else{
            $utdata = UserMeta::create([
                "meta_name"=>"user_chart_tune_data",
                "meta_value"=>json_encode($data),
                "user_id"=>$user->id
            ]);
        }
        $utdata->update(["meta_value"=>json_encode($data)]);
        return $data["current"];
    }
    public static function fork2(User $user,Instrument $instrument, $dataTime=null){
        $meta = 'user_tune_#'.$instrument->id;
        $time = time();
        $precision = 10;
        $current = 0;
        $changed = false;
        $userTune = UserTune::where('instrument_id',$instrument->id)->where('user_id',$user->id)->where('time','<=',$time)->orderBy('id','desc')->first();
        if(!is_null($dataTime) && !is_null($userTune) && $userTune->time == $dataTime ){
            // echo "Tune from history:".json_encode($userTune)."\n";
            return $userTune->coef;
        }
        $userMeta = UserMeta::user($user)->meta($meta)->first();

        if(is_null($userMeta) || $userMeta == false) return 1;
        $tune = json_decode($userMeta->meta_value,true);
        // echo "Tune :".$userMeta->meta_value."\n";
        // echo "Curr time: ".$time.' - '.date('Y-m-d H:i:s',$time)."\n";

        if(!isset($tune["started"]))$tune["started"]=$time+60;
        // echo "Tune time: ".$tune["started"].' - '.date('Y-m-d H:i:s',$tune["started"])."\n";
        // echo "last time: ".$tune["lasted"].' - '.date('Y-m-d H:i:s',$tune["lasted"])."\n";
        if(($time-$tune["started"])>0){
            $current = (isset($tune["current"]))?floatval($tune["current"]):0;
            $diffTime =  $time - (isset($tune["lasted"])?$tune["lasted"]:$time);
            // echo "Diff time: ".$diffTime."\n";
            // echo "Lavarage:  ".($precision*$tune["flying"])."\n";
            if($diffTime <=0) $current = $tune["current"];
            if( ($time - ($tune["started"]) ) >= $precision*$tune["flying"]) {$current = $tune["coef"];}
            else if(
                    ($tune["coef"]>0 && $current < $tune["coef"]) ||
                    ($tune["coef"]<0 && $current > $tune["coef"])
                ) {$changed=true;$current += $diffTime * ( $tune["coef"]/($precision*$tune["flying"]) );}
            else if(
                    ($tune["coef"]>0 && $current > $tune["coef"]) ||
                    ($tune["coef"]<0 && $current < $tune["coef"])
                ) {$changed=true;$current -= $diffTime * ( $tune["coef"]/($precision*$tune["flying"]) );}
            $tune["current"]=$current;
            $tune["lasted"]=$time;
            if($changed){
                // echo "Changed!\n";
                $userMeta->update(["meta_value"=>json_encode($tune)]);
                if(is_null($userTune) || $userTune->coef !=(1+$current) || $current!=0 ){
                    // echo "Tune chnaged was: ".(is_null($userTune)?'none':$userTune->coef)." now: ".$current."\n";
                    UserTune::create([
                        'instrument_id'=>$instrument->id,
                        'user_id'=>$user->id,
                        'time'=>$time,
                        'coef'=>(1+$current)
                    ]);
                }
            }

        }else {
            $current=$tune["current"];
        }
        // echo "Returning ".(1+$current)." (".$current.")\n";
        return (1+$current);
    }
    public static function risk(Instrument $instrument,$data){
        $ret = $data;
        $histos = ['open','close'];
        if($instrument->riskon == 0) return $ret;
        $prs = rand(800,1000)/1000;
        if($data instanceof Price){ // price data
            if($data->price < $instrument->low || $data->price > $instrument->high){
                $ret->price = rand(intval($instrument->low*self::$precision),intval($instrument->high*self::$precision))/self::$precision;;
                // $ret->price = (1+($instrument->low - $ret->price)/$instrument->low)*$ret->price*$prs;
            }
        }
        if($data instanceof Histo || $data instanceof HistoHour || $data instanceof HistoDay){ // histo data
            foreach($histos as $price){
                if($data->$price < $instrument->low || $data->$price > $instrument->high){
                    $ret->$price = rand(intval($instrument->low*self::$precision),intval($instrument->high*self::$precision))/self::$precision;;
                    // $ret->price = (1+($instrument->low - $ret->price)/$instrument->low)*$ret->price*$prs;
                }
            }

        }
        $ret->save();
        return $ret;
    }

};
?>
