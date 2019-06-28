<?php

namespace Vsb\Http\Controllers;

use Log;
use Cookie;
use App\User;
use App\UserMeta;
use App\UserHistory;
use App\UserTunePrice;
use Vsb\Model\Deal;
use Vsb\Model\DealStatus;
use Vsb\Model\DealHistory;
use Vsb\Model\Currency;
use Vsb\Model\Histo;
use Vsb\Model\Price;
use Vsb\Model\Option;
use Vsb\Model\Account;
use Vsb\Model\Instrument;
use Vsb\Model\InstrumentGroup;
use Vsb\Crm\Events\UserStateEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Vsb\Crm\Http\Controllers\TransactionController;
use Vsb\Crm\Http\Controllers\UserController;

class DealController extends Controller{
    protected  $trx;
    public function __construct(){
        $this->middleware('auth');
        $this->middleware('online');
        $this->trx = new TransactionController();
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $rq,$format='json',$id=false){
        $user = UserController::getUser($rq);
        $user = (!is_object($user) || is_null($user))?$rq->user():$user;
        $userMode = Cookie::get('cryptofx_mode','demo');
        // $res = Deal::where('id','>','9')
        $res = Deal::with(['user'=>function($query){
                $query->with(['manager','meta']);
            },'currency','instrument'=>function($query){
            $query->with(['from','to','source']);
        },'status','account']);
        if($id!==false) {
            $res =$res->where('id','=',$id);
        }
        else {
            $res->where('status_id','<',100);
            if($rq->input("search","false") !== "false") {
                $se = $rq->input('search','%');
                if(preg_match('/^#(\d+)/',$se,$m))$res->where('user_id',$m[1]);
                // else  $res->whereIn('user_id',User::where('name','like',"%{$se}%")->orWhere('surname','like',"%{$se}%")->orWhere('email','like',"%{$se}%")->orWhere('id','like',"%{$se}%")->pluck('id'))
                //             ->orWhere('id','like',"%{$se}%");
                else  $res->where(function($q)use($se){
                    $q->whereIn('user_id',User::where('name','like',"%{$se}%")->orWhere('surname','like',"%{$se}%")->orWhere('email','like',"%{$se}%")->orWhere('id','like',"%{$se}%")->pluck('id'))
                        ->orWhere('id','like',"%{$se}%");
                });

            }
            $res = $res->byInstrument($rq->input("instrument_id",false));
            $childs = $user->childs;
            $res->whereIn('account_id',Account::whereIn('type',($rq->has("account_type") && $rq->account_type!='false')?[$rq->account_type]:['real','demo'])->pluck('id'));
            if($rq->input("user_id","false") !== "false") $res->where('user_id',$rq->user_id);
            else if($user->rights_id<8) $res->whereIn('user_id',User::where(
                        function($uq)use($childs){
                            $uq->whereIn('parent_user_id',$childs)->orWhereIn('affilate_id',$childs);
                        }
                    )->pluck('id')
                );

            // $res = $res->whereIn('account_id',Account::where('type',$rq->input("account_type","demo"))->whereIn('user_id',$user->childs)->select('id')->get());
            if( $rq->has('status_id') && $rq->status_id!='false' ){
                $res->whereIn('status_id',preg_split('/,/m',$rq->status_id));
            }
            if($rq->input("date_from","false") !== "false") $res->where('created_at','>=',$rq->date_from);
            if($rq->input("date_to","false") !== "false") $res->where('created_at','<=',$rq->date_to);

            // if($rq->input("status","false") !== "false") $res->byStatus($rq->input("status"));
            if($rq->input("pnl","false") !== "false") $res->whereRaw('(amount + fee)'.(($rq->pnl=='profit')?'<':'>').' profit');
            if($rq->input("sort",false)!==false) foreach ($rq->input("sort") as $key => $value) $res =$res->orderBy($key,$value);
            else $res = $res->orderBy('id','desc');
        }

        $res = $res->paginate($rq->input('per_page',15));
        $ret = $res->toArray();
        foreach($ret["data"] as &$row){
            $row["is_tune"] = "N";
            // $um = UserMeta::where('user_id',$row['user_id'])->where('meta_name','user_tune_#'.$row['instrument_id'])->first();
            $um2 = UserMeta::where('user_id',$row['user_id'])->where('meta_name','user_tune_corida_#'.$row['instrument_id'])->first();
            if(is_null($um2) ){
                continue;
            }else{
                $corrida = json_decode($um2->meta_value);
                if($corrida->riskon!=1)continue;

            }
            $row["is_tune"] = "Y";
        }
        // $ret = json_encode($ret,JSON_UNESCAPED_UNICODE);
        // Log::debug($res->toSql());
        return ($format=='json')
                ?response()->json($ret,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)
                :(($id==false)
                    ?view('crm.deal.list',["deals"=>$ret])
                    :view('crm.content.deal.dashboard',[
                        "deal"=>$res->first(),
                        'price'=>Price::where('instrument_id','=',$res->first()->instrument->id)->orderBy('id','desc')->first()
                    ])
                );


    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $rq){
        $user = UserController::getUser($rq);
        return $this->openDeal($user,$rq->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  \Vsb\Crm\Deal  $deal
     * @return \Illuminate\Http\Response
     */
    public function show(Deal $deal){
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \Vsb\Crm\Deal  $deal
     * @return \Illuminate\Http\Response
     */
    public function edit(Deal $deal)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Vsb\Crm\Deal  $deal
     * @return \Illuminate\Http\Response
     */
    public function update(Request $rq, Deal $deal)
    {
        if(is_null($deal))
        {
            $deal = Deal::find($deal);
        }

        $deal->update($rq->all());
        $deal->load([
            'user'=>function($query){ $query->with(['manager','meta']); },
            'currency',
            'instrument'=>function($query){ $query->with(['from','to','source']); },
            'status',
            'account'
        ]);

        // event(new DepositEvent($object));

        return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
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
            $price = Price::where('instrument_id',$pair->id)->where('source_id',$pair->source_id)->orderBy('id','desc')->first();
            $utp = UserTunePrice::where('price_id',is_null($price)?0:$price->id)->where('user_id',$user->id)->first();
            $price = (!is_null($utp) && floatval($utp->price)>0)?$utp:$price;

            if(is_null($price)){
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.locked_for_now")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            $spread = ($direction==1)
                ?(1+floatval($pair->spread_buy)/100)
                :(1-floatval($pair->spread_sell)/100);
            $cur_price = floatval($price->price)
                *$spread;
            $atprice = $cur_price;
            $amount = $atprice*$volume*$pair->lot/$pair->pips;
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
            $trx = $this->trx->makeTransaction($creditData);
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
                $this->trx->rollback($creditData);
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
                $this->trx->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.Not_enough_amount")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            if($stopLost>=$amount){
                $this->trx->rollback($creditData);
                return response()->json([
                    "error"=>"1",
                    'code'=>'500',
                    "message"=>__("messages.Stop_lost_amount")
                ],500,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
            }
            if($takeProfit>0 && $takeProfit<=$amount){
                $this->trx->rollback($creditData);
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
            $trxFee = $this->trx->createTransaction($feeData);
            $amount -= $fee;
            if(!in_array($trxFee->code,["0","200"])){
                $this->trx->rollback($creditData);
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
            if(is_null($deal) && !is_null($creditData) && count($creditData)) $this->trx->rollback($creditData);
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
            $deal->update(["status_id" => $dealStatus->id]);
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
                if($makeTransactionAmount>0) {
                    $trx = $this->trx->makeTransaction([
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
            }else if($deal->status_id == 30){
                $makeTransactionAmount = floatval($deal->amount);
                $trx = true;
                if($makeTransactionAmount>0) {
                    $trx = $this->trx->makeTransaction([
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
            // $followers->delete();
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
            $this->trx->makeTransaction([
                'account'=>$deal->account_id,
                'type'=>'return',
                'user' => $user,
                'merchant'=>'1',
                'amount'=> $invested
            ]);
            $this->trx->makeTransaction([
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
    /**
     * Remove the specified resource from storage.
     *
     * @param  \Vsb\Crm\Deal  $deal
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $rq,Deal $deal = null){
        if($deal instanceof Deal){}else $deal = Deal::find($rq->deal_id);
        $deal->load([
            'user'=>function($query){ $query->with(['manager','meta']); },
            'currency',
            'instrument'=>function($query){ $query->with(['from','to','source']); },
            'status'
        ]);
        if(in_array($deal->status_id,[10,30])) return $this->closeDeal($deal->user,$deal,null);
        return response()->json($deal,200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }
    public function statuses(Request $rq){
        return response()->json(DealStatus::all(),200,['Content-Type' => 'application/json; charset=utf-8'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);

    }
}
