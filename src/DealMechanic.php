<?php namespace Vsb;
use DB;
use Log;
use App\Deal;
use App\Event;
use App\DealHistory;
use App\DealStatus;
use App\User;
use App\Currency;
use App\UserMeta;
use App\UserHistory;
use App\Account;
use App\Option;
use App\Price;
use App\Instrument;
use App\InstrumentGroup;
use App\UserTunePrice;
use App\Events\UserStateEvent;
use App\Http\Controllers\TransactionController;
use cryptofx\DataTune;

class DealMechanic{
    protected static $eventCallback = false;
    protected static $minMove = 1;
    public static function fork(Deal $dealInput, $price){
        return $dealInput->isForex()
            ?self::forex($dealInput,$price)
            :self::usual($dealInput,$price);
    }
    public static function forex(Deal $dealInput,$tick){
        $deal = Deal::find($dealInput->id);
        $user = User::find($deal->user_id);
        // lot * pips = $pipsMove
        $instrument = Instrument::find($deal->instrument_id);
        $ig = InstrumentGroup::find($user->pairgroup);
        // check if the base currency
        $crossExchange = 1;
        if($instrument->to->code!='USD'){
            $baseCurrency = Currency::where('code','USD')->first();
            if($instrument->to->code=='USD'){
                $crossExchange = 1/floatval($cross->price);
            }
            else {
                $cross = Instrument::where('from_currency_id',$instrument->to->id)->where('to_currency_id',$baseCurrency->id)->first();
                if(!is_null($cross)){
                    $crossExchange = floatval($cross->price);
                }
            }
        }
        $account = Account::find($deal->account_id);

        $dealDescription = '';
        $price = floatval($tick->price);
        $tradeAmount = floatval($deal->amount);

        if($deal->status_id==30){//delayed
            $atprice = floatval($deal->open_price);
            $lastprice = floatval($deal->close_price);
            if(
                ( $lastprice>$atprice && $atprice>=$price )// && $tick->volation==-1)
                                    ||
                ( $lastprice<$atprice && $atprice<=$price )//&& $tick->volation==1)
            ){
                $deal->update(["status_id"=>10]);
                DealHistory::create([
                    'deal_id'=>$deal->id,
                    'old_status_id'=>$deal->status_id,
                    'new_status_id'=>10,
                    'changed_user_id'=>$user->id,
                    'description'=>'Delayed deal is opened now'
                ]);
                $deal->events()->create(['type'=>'open','user_id'=>$deal->user_id]);
            }
        }
        if($deal->status_id==10){//opened
            $swap = floatval($ig->dayswap)/100;
            if($swap>0){
                $todayTimestamp = time();
                $todayTimestamp = $todayTimestamp - ($todayTimestamp%(24*60*60));
                if( ($deal->created_at->timestamp - ($deal->created_at->timestamp%(24*60*60))) < $todayTimestamp){
                    $checkMetaName = 'trade#'.$deal->id."_swap_".$todayTimestamp;
                    $checkMeta  = $user->meta()->where('meta_name',$checkMetaName)->first();
                    if(is_null($checkMeta)){
                        $value = floatval($deal->invested)*floatval($deal->multiplier)*$swap;
                        if($value>0){
                            $trx = new TransactionController();
                            $user->meta()->create(['meta_name'=>$checkMetaName,'meta_value'=>$value]);
                            $trx->makeTransaction([
                                'account'=>$account->id,
                                'type'=>'swap',
                                'user' => $user,
                                'merchant'=>'1',
                                'amount'=>$value,
                            ]);
                        }
                    }
                }
            }
            // $pipsCount = floor((floatval($price)-floatval($deal->close_price))/$pips) +((floatval($price)-floatval($deal->close_price)>=0)?0:1);
            $pips = floatval($instrument->pips);
            $lot = floatval($deal->lot);
            $open = floatval($deal->open_price);
            $pipsCount = intval(($price-$open)/$pips);
            $volume = floatval($deal->volume);
            $contract = $volume*$lot;
            $direction = intval($deal->direction);
            $profit = $direction*( ($contract*$price) - ($contract*$open) );
            $profit *= $crossExchange;
            $invested = floatval($deal->invested);

            $marginCall = $invested*($user->margincall?$user->margincall:20)/100;
            $stopOut = $invested*($user->stopout?$user->stopout:$ig->stopout)/100;
            if($profit>0)$deal->volation=1;
            else if($profit<0)$deal->volation=-1;
            $deal->profit = $profit;
            $deal->close_price = $price;
            // if($profit!=0){
            //     Log::debug("{$user->title}\t#{$deal->id} {$deal->instrument->title}\tvolume[{$tradeAmount}x{$deal->multiplier}/ {$lot}]: {$volume} pipCounts[{$price} - {$deal->close_price} / {$pips}]: {$pipsCount} cross: {$crossExchange} min: ".self::$minMove." profit: {$profit}");
            //     $account->amount+=$profit;
            //     $account->save();
            // }
            // Log::debug('Forex #'.$dealInput->id.' lot: '.$lot.' cross: '.$crossExchange.' volume: '.($tradeAmount*$deal->multiplier).' pips: '.($deal->direction*(floatval($price)-floatval($deal->close_price))).' trade:'.$profit.' Balance: '.$account->amount);
            $dealController = new \App\Http\Controllers\DealController();
            if($deal->stop_low>0){
                if($direction > 0 && $price<=$deal->stop_low){
                    $deal->status_id = 20;
                    $dealController->closeDeal($user,$deal,$price,'Stop lost signal');
                }
                else if($direction < 0 && $price>=$deal->stop_low){
                    $deal->status_id = 20;
                    $dealController->closeDeal($user,$deal,$price,'Stop lost signal');
                }
            }
            if($deal->stop_high>0){
                if($direction > 0 && $price>=$deal->stop_high ){
                    $deal->status_id = 20;
                    $dealController->closeDeal($user,$deal,$price,'Take profit signal');
                }
                else if($direction < 0 && $price<=$deal->stop_high ){
                    $deal->status_id = 20;
                    $dealController->closeDeal($user,$deal,$price,'Take profit signal');
                }
            }
            if($invested+$profit < $marginCall){
                //marginCall Event
                $mcum = $user->meta()->where('meta_name','margincall_'.$deal->id)->first();
                if(is_null($mcum)){
                    $deal->events()->create(['type'=>'margincall','user_id'=>$deal->user_id]);
                    $user->meta()->create([
                        'meta_name'=>'margincall_'.$deal->id,
                        'meta_value'=>1
                    ]);
                }

            }
            if($invested+$profit < $stopOut){
                //stopOut Event
                $dealController->closeDeal($user,$deal,$price,'Stopout signal');
            }
            $deal->save();
            //autoclose tune
            if($deal->status_id == 20){
                $userMeta = UserMeta::byUser($user)->meta('user_tune_corida_#'.$deal->instrument_id)->first();
                if(!is_null($userMeta) && $userMeta !== false) {
                    //autoclose deal
                    $tune = json_decode($userMeta->meta_value);
                    if(isset($tune->riskon)) {
                        $tune->riskon=0;
                        $userMeta->update(['meta_value'=>json_encode($tune)]);
                    }
                }
                UserHistory::create(['user_id'=>$user->id,'type'=>'deal.drop','object_id'=>$deal->id,'object_type'=>'deal','description'=>$dealDescription ]);
                $deal->events()->create(['type'=>'close','user_id'=>$deal->user_id]);
            }
        }
    }
    public static function usual(Deal $dealInput,$tick){
        DB::beginTransaction();
        try{
            $deal = Deal::with(['instrument','account','user'])->lockForUpdate()->find($dealInput->id);
            if(is_null($deal)){
                DB::commit();
                return;
            }
            if(floatval($deal->close_price) == floatval($tick->price)){
                DB::commit();
                return;
            }
            if($deal->status_id==30) {
                DB::commit();
                return;
            }
            $user = $deal->user;
            $account = $deal->account;
            $ig = InstrumentGroup::find($user->pairgroup);
            $dealDescription = '';
            $price = floatval($tick->price);
            $dealUpdate = ["close_price" => $price];
            $tradeAmount = floatval($deal->amount);
            if($deal->status_id==30){//delayed
                $atprice = floatval($deal->open_price);
                $lastprice = floatval($deal->close_price);
                if(
                    ( $lastprice>$atprice && $atprice>=$price )// && $tick->volation==-1)
                                        ||
                    ( $lastprice<$atprice && $atprice<=$price )//&& $tick->volation==1)
                ){
                    $deal->update(["status_id"=>10]);
                    DealHistory::create([
                        'deal_id'=>$deal->id,
                        'old_status_id'=>$deal->status_id,
                        'new_status_id'=>10,
                        'changed_user_id'=>$user->id,
                        'description'=>'Delayed deal is opened now'
                    ]);
                    $deal->events()->create(['type'=>'open','user_id'=>$deal->user_id]);
                }
            }
            if($deal->status_id==10 ){//opened
                $swap = is_null($ig)?0:(floatval($ig->dayswap)/100);
                if($swap>0){
                    $todayTimestamp = time();
                    $todayTimestamp = $todayTimestamp - ($todayTimestamp%(24*60*60));
                    if( ($deal->created_at->timestamp - ($deal->created_at->timestamp%(24*60*60))) < $todayTimestamp){
                        $checkMetaName = 'trade#'.$deal->id."_swap_".$todayTimestamp;
                        $checkMeta  = $user->meta()->where('meta_name',$checkMetaName)->first();
                        if(is_null($checkMeta)){
                            $value = floatval($deal->invested)*floatval($deal->multiplier)*$swap;
                            echo "need day swap: {$value} {$swap}%\n";
                            if($value>0){
                                $trx = new TransactionController();
                                $tradeAmount -= $value;
                                $user->meta()->create(['meta_name'=>$checkMetaName,'meta_value'=>$value]);
                                $trx->createTransaction([
                                    'account'=>$account->id,
                                    'type'=>'fee',
                                    'user' => $user,
                                    'merchant'=>'1',
                                    'amount'=>$value,
                                ]);
                                UserHistory::create(['user_id'=>$user->id,'type'=>'deal.swap','object_id'=>$deal->id,'object_type'=>'deal','description'=>'Daily swap $'.$value ]);
                            }
                        }
                    }
                }
                $profit = (floatval($deal->open_price)==0)?0:
                            ($tradeAmount
                            *$deal->multiplier
                            *(
                                $deal->direction
                                    *(floatval($price)/floatval($deal->open_price)-1)
                            ));

                $dealController = new \App\Http\Controllers\DealController();
                $dealUpdate["profit"] =$profit;
                if($deal->stop_low>0 && ($tradeAmount+$profit)<=$deal->stop_low){
                    $profit = $deal->stop_low-$tradeAmount;
                    // $dealUpdate["profit"] =$profit;
                    $dealController->closeDeal($user,$deal,$price,'Stop lost signal',$deal->stop_low);
                    unset($dealUpdate['profit']);
                }
                else if($deal->stop_high>0 && ($tradeAmount+$profit)>=$deal->stop_high){
                    $profit = $deal->stop_high-$tradeAmount;
                    // $dealUpdate["profit"] =$profit;
                    $dealController->closeDeal($user,$deal,$price,'Stop profit signal',$deal->stop_high);
                    unset($dealUpdate['profit']);
                }
                else if(($tradeAmount+$profit)<=0){
                    $profit = $tradeAmount-floatval($deal->invested);
                    // $dealUpdate["profit"] =$profit;
                    $dealController->closeDeal($user,$deal,$price,'Margin call',0);
                    unset($dealUpdate['profit']);
                }
                else {
                    $dealUpdate['volation'] = 0;
                    $dealUpdate['amount'] = $tradeAmount;
                    if(floatval($deal->profit)<floatval($dealUpdate['profit']))$dealUpdate['volation']=1;
                    else if(floatval($deal->profit)>floatval($dealUpdate['profit']))$dealUpdate['volation']=-1;
                }
            }
            $deal->update($dealUpdate);
        }
        catch(\Exception $e){
            DB::rollback();
            Log::error($e);
        }
        DB::commit();
    }
    protected static function userMargin(User $user){
        $response = [
            "balance"=>0,
            "funds"=>0,
            "margin"=>0,
            "marginNoLeverage"=>0,
            "marginFree"=>0,
            "marginCall"=>0,
            "marginLevel"=>0,
            "stopOut"=>0,
            "stopOutPercent"=>0,
        ];
        foreach ($user->deal as $trade) {

        }
    }
};
?>
