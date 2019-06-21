<?php
namespace Vsb\Facades;

use Log;
use Cookie;
use App\User;
use App\UserMeta;
use App\UserHistory;
use App\UserTunePrice;
use App\Deal;
use App\DealStatus;
use App\DealHistory;
use App\Currency;
use App\Histo;
use App\Price;
use App\Option;
use App\Account;
use App\Instrument;
use App\InstrumentGroup;
use App\Events\UserStateEvent;

use App\Http\Controllers\UserController;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Facade;

class DealManager extends Facade {
    protected $pm;
    public function __construct(){
        $this->pm = app('vsb.payments');
    }
    public  function openDeal(User $user,$data){
        $dealType = 'xcryptex';
        $atprice = isset($data["atprice"])?$data["atprice"]:0;
        $amount = floatval (isset($data["amount"])?$data["amount"]:0);
        $invested = $amount;
        $fee = 0;
        $multiplier = isset($data['multiplier'])?$data['multiplier']:1;
        $direction = isset($data['direction'])?$data['direction']:1;
        $creditData = null;
        $canTrade = UserMeta::where('user_id',$user->id)->where('meta_name','can_trade')->first();
        $canTrade = is_null($canTrade)?false:($canTrade->meta_value=="true" || $canTrade->meta_value=="1");
        if(!$canTrade){
            return response()->json([
                "error"=>'-1',
                'code'=>'500',
                "message"=>__("messages.cant_trade")
            ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        if(floatval($amount)<=0)
            return response()->json([
                "code"=>500,
                "error"=>-1,
                "message"=>__("messages.amount_0")
            ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

        $account = Account::where('user_id',$user->id)->where('type',isset($data['account_type'])?$data['account_type']:'demo')->first();
        $pair = Instrument::find($data['instrument_id']);
        $ig = InstrumentGroup::find($user->pairgroup);
        $isforex = ($ig->type=='forex');//Option::where('name','use_forex_like_trade')->first();
        $dealStatus = DealStatus::where('code',isset($data['status'])?$data['status']:'open')->first();
        $takeProfit = preg_replace('/(\d+)(\.|,)$/i',"$1",isset($data["stop_high"])?$data["stop_high"]:0);
        $stopLost = isset($data["stop_low"])?$data["stop_low"]:0;
        if(is_null($takeProfit))$takeProfit=0;
        if(is_null($stopLost))$stopLost=0;

        $takeProfit = floatval($takeProfit);
        $stopLost = floatval($stopLost);
        $volume = isset($data['volume'])?$data['volume']:0;
        if($isforex){
            $dealType = 'forex';
            $utp = UserTunePrice::where('time','>',time()-3*60*60)->where('user_id',$user->id)->orderBy('id','desc')->first();
            $price = (!is_null($utp) && floatval($utp->price)>0)?floatval($utp->price):floatval($pair->price);

            if($price<=0){
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.locked_for_now")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            $spread = ($direction==1)
                ?(1+floatval($ig->spread_buy)/100)
                :(1-floatval($ig->spread_sell)/100);
            $cur_price = $price*$spread;
            $atprice = $cur_price;
            $amount = $volume*$pair->lot/$multiplier;
            $profit = $direction*( ($amount*$price) - ($amount*$cur_price) );
            $tradeAmount = Deal::where('account_id',$account->id)->whereIn('status_id',[10,30])->sum('invested');
            
            if( floatval($account->amount)-floatval($tradeAmount) < $amount) return response()->json([
                "error"=>"1",
                'code'=>'500',
                "message"=>__("messages.Not_enough_amount")
            ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

            // $amount = $atprice*$volume*$pair->lot/$pair->pips;
        }
        else{
            $maxFee = $amount/2;
            $creditData = [
                'uid'=>'trdpn'.time().$account->id,
                'account'=>$account->id,
                'type'=>'credit',
                'user' => $user,
                'merchant'=>'1',
                'amount'=>-$amount,
            ];
            Log::debug('sm trade opening...',$creditData);
            $trx = $this->pm->makeTransaction($creditData);
            if(!in_array($trx->code,["0","200"]))return response()->json($trx,$trx->code,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            Log::debug("Looking for price ".$data["atprice"]);
            // $atprice = $data["atprice"];

            // $price = Price::where('instrument_id',$pair->id)->where('source_id',$pair->source_id)->where('price',floatval($data["atprice"]))->orderBy('id','desc')->first();
            $price = Price::where('instrument_id',$pair->id)->where('source_id',$pair->source_id)->orderBy('id','desc')->first();
            $utp = is_null($price)?null:UserTunePrice::where('instrument_id',$pair->id)->where('user_id',$user->id)->where('time','>',$price->time-60)->orderBy('id','desc')->first();
            $price = (!is_null($utp) && floatval($utp->price)>0)?$utp:$price;
            if(is_null($price) || floatval($price->price)==0){
                $price = floatval($pair->price);
            }
            else $price = floatval($price->price);
            if(is_null($atprice) || $atprice <= 0 ) {
                $atprice = $price;
            }
            if(is_null($utp)){
                $diff = 1- (($atprice>$price)?$price/$atprice:$atprice/$price);
                if($diff>0.1)$atprice=$price;
            }
            else $atprice = floatval($utp->price);

            if($atprice <= 0 ){
                $this->pm->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__('messages.not_availiable_now'),
                    "trace"=>[]
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }

            $fee = floatval($amount)*floatval($multiplier)*floatval($ig->commission/100);
            $fee = ($fee>$maxFee)?$maxFee:$fee;

            if($fee>=$amount){
                $this->pm->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.Not_enough_amount")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            if($stopLost>=$amount){
                $this->pm->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.Stop_lost_amount")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            if($takeProfit>0 && $takeProfit<=$amount){
                $this->pm->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.Value_Take_profit")
                    // "message"=>{__('messages.Menu')}
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            $feeData = [
                'uid'=>'trdfee'.time().$account->id,
                'account'=>$account->id,
                'type'=>'fee',
                'user' => $user,
                'merchant'=>'1',
                'amount'=>$fee,
            ];
            $trxFee = $this->pm->createTransaction($feeData);
            $amount -= $fee;
            if(!in_array($trxFee->code,["0","200"])){
                $this->pm->rollback($creditData);
                return response()->json($trxFee,$trxFee->code,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }

            $invested = $amount+$fee;
        }
        $dealData = [
            'status_id' => '10',
            'instrument_id'=>$pair->id,
            'user_id'=>$user->id,
            'open_price'=>$atprice,
            'close_price'=>$atprice,
            'direction'=>$direction,
            'stop_high'=>$takeProfit,
            'stop_low'=>$stopLost,
            'amount'=>$amount,
            'currency_id'=>$account->currency_id,
            'multiplier'=>$multiplier,
            'account_id'=>$account->id,
            'fee'=>$fee,
            'invested'=>$invested,
            'type'=>$dealType,
            'volume'=>$volume,
            'lot'=>$pair->lot,
            'pips'=>$pair->pips,
        ];
        if( isset($data["delayed"]) ){
            $delayed = $data["delayed"];
            if( $delayed=='true' ){
                // $dealStatus = DealStatus::where('code',isset($data['status'])?$data['status']:'delayed')->first();
                $dealData['open_price']=$data["atprice"];
                $dealData['status_id'] = 30;
            }
        }
        Log::debug('Deal open: ',$dealData);
        $deal = null;
        try{
            $deal = Deal::create($dealData);
            $deal->events()->create(['type'=>'new','user_id'=>$deal->user_id]);
            DealHistory::create([
                'deal_id'=>$deal->id,
                'old_status_id'=>$dealData['status_id'],
                'new_status_id'=>$dealData['status_id'],
                'changed_user_id'=>$user->id,
                'description'=>'Interface opened'
            ]);
            $deal->load(['user'=>function($query){
                    $query->with(['manager','meta']);
                },'currency','instrument'=>function($query){
                $query->with(['from','to','source']);
            },'status','account']);
            $deal = json_decode(json_encode($deal),true);
            $deal['commission']=$ig->commission;
            $deal['profit']=0;
            $descriptionHistory = $pair->title;//''
            if(isset($data['following'])){
                $trade = Deal::find($data["following"]);
                if(!is_null($trade)){
                    $sb = $user->meta()->where('meta_name','subscribe_follow_trade#'.$trade->id)->first();
                    $subscribe = is_null($sb)?[
                        "deal"=>"",
                        "partner"=>$trade->user_id
                    ]:json_decode($sb->meta_value,true);
                    $subscribe["deal"] = (strlen($subscribe["deal"])?$subscribe["deal"].",":"").$deal["id"];
                    if(is_null($sb))$user->meta()->create([
                        'meta_name'=>'subscribe_follow_trade#'.$trade->id,
                        'meta_value'=> json_encode($subscribe)
                    ]);
                    else $sb->update(['meta_value'=> json_encode($subscribe)]);
                    $descriptionHistory = 'Followed deal #'.$data["following"].' '.$pair->title;//''
                }
            }else{
                $aot = Option::where('name','auto_follow_trade')->first();
                if(!is_null($aot) && $aot->is_set() && $user->rights_id == 3){
                    foreach(UserMeta::where('meta_name','can_follow')->get() as $followers){
                        $v = json_decode($followers->meta_value);
                        if($v->partner == $user->id && $v->can=="true"){
                            $data['following'] = $deal["id"];
                            try{ $this->openDeal($followers->user,$data); }
                            catch(\Exception $e){}

                        }
                    }
                }
            }
            UserHistory::create(['user_id'=>$user->id,'type'=>'deal.open','object_id'=>$deal["id"],'object_type'=>'deal','description'=>$descriptionHistory]);
            event( new UserStateEvent($user->id) );
        }
        catch(\Exception $e){
            if(is_null($deal) && !is_null($creditData) && count($creditData)) $this->pm->rollback($creditData);
            if(!is_null($user)) {
                $desc = "Try open deal: ";
                if(isset($dealData))$desc.=json_encode($dealData);
                $desc.=" ".$e->getMessage();
                UserHistory::create(['user_id'=>$user->id,'type'=>'error','description'=>$desc]);
            }
            Log::debug('Deal::create exception'.$e->getMessage());
            Log::error($e);
            return response()->json([
                "error"=>"1",
                'code'=>'500',
                "message"=>__("messages.Call_your_manager"),
                'trace'=>$e->getTrace()
            ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
    public function closeDeal(User $user,Deal $deal,$current_price, $description=false,$tradeAmount=false){
        if($deal->status_id==20) {
            $deal->load(['instrument'=>function($q){$q->with(['from','to']);}]);
            return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $dealStatus = DealStatus::where('code','close')->first();
        $instrument = Instrument::find($deal->instrument_id);
        if($deal->type=='forex'){
            if(in_array($deal->status_id,[10,30])){
                $lot = floatval($deal->lot);
                $open = floatval($deal->open_price);
                $price = floatval($deal->close_price);
                $volume = floatval($deal->volume);
                $contract = $volume*$lot;
                $direction = intval($deal->direction);
                $profit = $direction*( ($contract*$price) - ($contract*$open) );

                if($profit!=0){
                    $trx = $this->pm->makeTransaction([
                        'uid'=>'trdcls'.$deal->id.$deal->account_id,
                        'account'=>$deal->account_id,
                        'type'=> ($profit>0)?'debit':'credit',
                        'user' => $user,
                        'merchant'=>'1',
                        'amount'=> $profit
                    ]);
                }
                $description = ($description!==false)?$description:"Interface closed";
                $deal->update(["status_id" => 20,"profit"=>$profit]);
                UserHistory::create(['user_id'=>$user->id,'type'=>'deal.drop','object_id'=>$deal->id,'object_type'=>'deal','description'=>$description ]);
                $deal->events()->create(['type'=>'close','user_id'=>$user->id]);
            }
        }
        else{
            $makeTransactionAmount  = floatval($deal->amount);
            $profit = 0;
            $priceVal = floatval($deal->close_price);
            $account = Account::find($deal->account_id);
            $originalPriceVal = is_null($current_price)?floatval($instrument->price):floatval($current_price);
            if($deal->status_id==10){
                $utp = null;
                if(is_null($current_price)){
                    $utp = UserTunePrice::where('instrument_id',$deal->instrument_id)->where('user_id',$deal->user_id)->where('time','>',time()-3*60)->orderBy('id','desc')->first();
                    if(!is_null($utp)){
                        $originalPriceVal = floatval($utp->price);
                    }
                }
                $profit = ($deal->open_price==0)?0:floatval($deal->multiplier)*$deal->direction*($originalPriceVal/floatval($deal->open_price) - 1) * floatval($deal->amount);
                $profit = ($tradeAmount!==false)?($tradeAmount-$makeTransactionAmount):$profit; // check for special amount
                $makeTransactionAmount = ($tradeAmount!==false)?$tradeAmount:($makeTransactionAmount+$profit); // check for special amount
                $makeTransactionAmount = ($makeTransactionAmount<0)?0:$makeTransactionAmount;
                $description = ($description!==false)?$description:"Interface closed";
                // $description.=" onClose params[".json_encode(["tick"=>$current_price,"tradeAmount"=>$tradeAmount])."]";
                $description.=" onClose params[".json_encode(["tick"=>$current_price,"tradeAmount"=>$tradeAmount,"makeTransactionAmount"=>$makeTransactionAmount,"profit"=>$profit, "closePrice" => $originalPriceVal, "in_request_price"=>$current_price, "original_price"=>$originalPriceVal, "tuning"=>is_null($utp)?' no':$utp->price])."]";
                $trx = true;
                if( floatval($deal->stop_low) > 0 && $makeTransactionAmount < floatval($deal->stop_low) ){
                    $makeTransactionAmount = floatval($deal->stop_low);
                    $profit = $makeTransactionAmount - floatval($deal->amount);
                }
                else if( floatval($deal->stop_high) > 0 && $makeTransactionAmount > floatval($deal->stop_high) ){
                    $makeTransactionAmount = floatval($deal->stop_high);
                    $profit = $makeTransactionAmount - floatval($deal->amount);
                }
                else if( $makeTransactionAmount <= 0 ){
                    $profit = 0 - floatval($deal->amount);
                }
                if( $trx !== false ) {
                    $deal->update([
                        "profit"=>$profit,
                        "close_price"=>$originalPriceVal,
                        "status_id" => $dealStatus->id
                    ]);
                    DealHistory::create([
                        'deal_id'=>$deal->id,
                        'old_status_id'=>$deal->status_id,
                        'new_status_id'=>$dealStatus->id,
                        'changed_user_id'=>$user->id,
                        'description'=>'Interface closed'
                    ]);
                    UserHistory::create(['user_id'=>$user->id,'type'=>'deal.drop','object_id'=>$deal->id,'object_type'=>'deal','description'=>$description ]);
                    $deal->events()->create(['type'=>'close','user_id'=>$deal->user_id]);
                }

                $userMeta = UserMeta::byUser($user)->meta('user_tune_corida_#'.$deal->instrument_id)->first();
                if(!is_null($userMeta) && $userMeta !== false) {
                    //autoclose deal
                    $tune = json_decode($userMeta->meta_value);
                    if(isset($tune->riskon) && isset($tune->deal_id) && $tune->deal_id == $deal->id) {
                        $tune->riskon=0;
                        $userMeta->update(['meta_value'=>json_encode($tune)]);
                    }
                }
            }else if($deal->status_id == 30){
                $makeTransactionAmount = floatval($deal->amount);
                $trx = true;
                if($makeTransactionAmount>0) {
                    $trx = $this->pm->makeTransaction([
                        'uid'=>'trdcls'.$deal->id.$account->id,
                        'account'=>$account->id,
                        'type'=>'debit',
                        'user' => $user,
                        'merchant'=>'1',
                        'amount'=> $makeTransactionAmount
                    ]);

                }
                if( $trx !== false ) {
                    $deal->update([
                        "profit"=>$profit,
                        "close_price"=>$originalPriceVal,
                        "status_id" => $dealStatus->id
                    ]);
                    DealHistory::create([
                        'deal_id'=>$deal->id,
                        'old_status_id'=>$deal->status_id,
                        'new_status_id'=>$dealStatus->id,
                        'changed_user_id'=>$user->id,
                        'description'=>'Interface closed'
                    ]);
                    UserHistory::create(['user_id'=>$user->id,'type'=>'deal.drop','object_id'=>$deal->id,'object_type'=>'deal','description'=>$description ]);
                    $deal->events()->create(['type'=>'close','user_id'=>$deal->user_id]);
                }

                $userMeta = UserMeta::byUser($user)->meta('user_tune_corida_#'.$deal->instrument_id)->first();
                if(!is_null($userMeta) && $userMeta !== false) {
                    //autoclose deal
                    $tune = json_decode($userMeta->meta_value);
                    if(isset($tune->riskon) && isset($tune->deal_id) && $tune->deal_id == $deal->id) {
                        $tune->riskon=0;
                        $userMeta->update(['meta_value'=>json_encode($tune)]);
                    }
                }
            }
        }

        event( new UserStateEvent($user->id) );
        foreach(UserMeta::where('meta_name','subscribe_follow_trade#'.$deal->id)->get() as $followers){
            Log::debug('Followers autoclose:'.json_encode($followers));
            $v = json_decode($followers->meta_value);
            if($v->partner == $user->id){ // may be it's more than enough
                Log::debug('Follower trades:',explode(",",$v->deal));
                foreach (explode(",",$v->deal) as $deal_id) {
                    $fd = Deal::find($deal_id);
                    $fu = User::find($fd->user_id);
                    if(!is_null($fd)) $this->closeDeal($fu,$fd,$current_price);
                }
            }
        }
        UserHistory::create(['user_id'=>$user->id,'type'=>'deal.drop','object_id'=>$deal->id,'object_type'=>'deal','description'=>$description ]);
        $deal->events()->create(['type'=>'close','user_id'=>$deal->user_id]);;
        $deal->load([
            'user'=>function($query){ $query->with(['manager','meta']); },
            'currency',
            'instrument'=>function($query){ $query->with(['from','to','source']); },
            'status',
            'account'
        ]);
        return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
    public function returnDeal(User $user, Deal $deal){
        $dealProfit = floatval($deal->profit);

        $amount = floatval($deal->amount);
        $profit = $dealProfit+$amount;
        $invested = floatval($deal->invested);
        if($profit>0){
            $this->pm->makeTransaction([
                'account'=>$deal->account_id,
                'type'=>'return',
                'user' => $user,
                'merchant'=>'1',
                'amount'=> $invested
            ]);
            $this->pm->makeTransaction([
                'account'=>$deal->account_id,
                'type'=>'credit',
                'user' => $user,
                'merchant'=>'1',
                'amount'=> $profit
            ]);
        }
        $deal->update(["status_id"=>100]);
        return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

}
