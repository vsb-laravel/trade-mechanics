<?php

namespace Vsb\Listeners;

use DB;
use Log;
use App\Instrument;
use App\Histo;
use App\HistoHour;
use App\HistoDay;
use App\Deal;
use App\DealStatus;
use App\User;
use App\UserMeta;
use App\Source;
use App\Events\PriceEvent;
use cryptofx\DealMechanic;
use cryptofx\DataTune;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

// class PriceEventListener
class PriceEventListener implements ShouldQueue
{
    public $queue = "prices";
    public $connection ="redis";
    protected $price = null;
    protected $pair = null;
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    public function tags()
    {
        $tags = ['price'];
        if(!is_null($this->price))$tags['pair:'.$this->price->instrument_id];
        return $tags;
    }
    /**
     * Handle the event.
     *
     * @param  PriceEvent  $event
     * @return void
     */
    public function handle(PriceEvent $event)
    {
        $price = $event->price;
        $this->price = $event->price;
        $time = time();
        if($time - intval($price->time) > 10) return;
        $this->pair = Instrument::find($price->instrument_id);
        if(!is_null($this->pair) && $this->pair->source_id == $price->source_id){
            $vo = (floatval($this->pair->price) < floatval($price->price))?1:-1;
            $this->pair->update([
                "price"=>floatval($price->price),
                "volation"=>$vo
            ]);
        }
        $this->tune($price);
        $this->histo($price);
        $this->deal($price);
    }
    public function tune($price){
        $pair = is_null($this->pair)?Instrument::find($price->instrument_id):$this->pair;
        $umcs = UserMeta::where('meta_name','user_tune_corida_#'.$pair->id)->get();
        foreach ($umcs as $umc) {
            // Log::debug("Price tune: source:{$price->source_id} pair:{$price->instrument_id} for user_id: {$umc->user_id}");
            DataTune::corrida($umc->user,$pair,$price);
        }
    }
    public function histo($price){
        try{
            $tm = $price->time - ($price->time%60);
            $th = $price->time - ($price->time%(3600));
            $td = $price->time - ($price->time%(24*3600));
            $time=[
                "minute" => [
                    $tm,
                    60+$tm,
                ],
                "hour" => [
                    $th,
                    (60*60)+$th,
                ],
                "day" => [
                    $td,
                    (24*60*60)+$td,
                ],
            ];

            $source = Source::find($price->source_id);
            $pair = is_null($this->pair)?Instrument::find($price->instrument_id):$this->pair;
            $h = Histo::where('instrument_id',$price->instrument_id)->orderBy('id','desc')->first();
            if(!is_null($h)){
                if( ( $price->time < $h->time  || ( $h->time > $time["minute"][1]) )) {
                    // Log::debug('old price '.date("H:i:s",$h->time). ' vs '.date("H:i:s",$price->time));
                    return;
                }
                else if( $h->time < $time["minute"][0]) {
                    // Log::debug('new price '.date("H:i:s",$h->time). ' vs '.date("H:i:s",$price->time));
                    $h=null;
                }
            }
            if(is_null($h)) Histo::create([
                'instrument_id'=>$price->instrument_id,
                'source_id'=>$price->source_id,
                'open'=>floatval($price->price)*floatval($pair->multiplex),
                'close'=>floatval($price->price)*floatval($pair->multiplex),
                'low'=>floatval($price->price)*floatval($pair->multiplex),
                'high'=>floatval($price->price)*floatval($pair->multiplex),
                'volumefrom'=>1,
                'volumeto'=>1,
                'time'=>$tm,
                'volation'=>0,
                'exchange' => $source->name
            ]);
            else{
                $changed = false;
                if(floatval($price->price)>floatval($h->high)){$h->high=$price->price;$h->volation=1;$changed=true;}
                if(floatval($price->price)<floatval($h->low)){$h->low=$price->price;$h->volation=-1;$changed=true;}
                if($h->close!=$price->price){$h->close=$price->price;$changed=true;}
                if($changed) {
                    // Log::debug("Histominute changed\n\t candle[".date("H:i:s",$h->time)."]=".json_encode($h)."\n\t nprice[".date("H:i:s",$price->time)."]=".json_encode($price));
                    $h->save();
                }
            }

            $h = HistoHour::where('instrument_id',$price->instrument_id)->whereBetween('time',$time["hour"])->first();
            if(is_null($h)) HistoHour::create([
                'instrument_id'=>$price->instrument_id,
                'source_id'=>$price->source_id,
                'open'=>floatval($price->price)*floatval($pair->multiplex),
                'close'=>floatval($price->price)*floatval($pair->multiplex),
                'low'=>floatval($price->price)*floatval($pair->multiplex),
                'high'=>floatval($price->price)*floatval($pair->multiplex),
                'volumefrom'=>1,
                'volumeto'=>1,
                'time'=>$th,
                'volation'=>0,
                'exchange' => $source->name
            ]);
            else{
                if(floatval($price->price)>floatval($h->high)){$h->high=$price->price;$h->volation=1;}
                if(floatval($price->price)<floatval($h->low)){$h->low=$price->price;$h->volation=-1;}
                $h->close=$price->price;
                $h->save();
            }
            $h = HistoDay::where('instrument_id',$price->instrument_id)->whereBetween('time',$time["day"])->first();
            if(is_null($h)) HistoDay::create([
                'instrument_id'=>$price->instrument_id,
                'source_id'=>$price->source_id,
                'open'=>floatval($price->price)*floatval($pair->multiplex),
                'close'=>floatval($price->price)*floatval($pair->multiplex),
                'low'=>floatval($price->price)*floatval($pair->multiplex),
                'high'=>floatval($price->price)*floatval($pair->multiplex),
                'volumefrom'=>1,
                'volumeto'=>1,
                'time'=>$td,
                'volation'=>0,
                'exchange' => $source->name
            ]);
            else{
                if(floatval($price->price)>floatval($h->high)){$h->high=$price->price;$h->volation=1;}
                if(floatval($price->price)<floatval($h->low)){$h->low=$price->price;$h->volation=-1;}
                $h->close=$price->price;
                $h->save();
            }
        }
        catch(\Exception $e){
            Log::error($e);
        }
    }
    public function deal_1($price){
        $trade = null;
        $instrument = Instrument::find($price->instrument_id);
        while(true){
            DB::beginTransaction();
            try{
                $trade = Deal::where('instrument_id',$price->instrument_id)//with(['instrument','account','user'])
                    ->whereIn('status_id',[10,30])
                    ->whereNotIn('user_id',UserMeta::where('meta_name','user_tune_corida_#'.$price->instrument_id)->pluck('user_id'))
                    ->orderBy('id')->lockForUpdate()->first();
                if(is_null($trade))break;
                $args = [
                    "instrument"=>$instrument
                ];
                DealMechanic::fork($trade,$price,$args);
            }
            catch(\Exception $e){
                DB::rollback();
                Log::error($e);
            }
            DB::commit();
        };
    }
    public function deal($price){
        $trades = Deal::where('instrument_id',$price->instrument_id)//with(['instrument','account','user'])
            ->whereIn('status_id',[10,30])
            ->whereNotIn('user_id',UserMeta::where('meta_name','user_tune_corida_#'.$price->instrument_id)->pluck('user_id'))
            ->orderBy('id')->get();

        foreach ($trades as $deal) {
            try{

                DealMechanic::fork($deal,$price);
            }
            catch(\Exception $e){
                DB::rollback();
                Log::error($e);
            }
            DB::commit();
        }
    }
}
