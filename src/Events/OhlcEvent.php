<?php

namespace Vsb\Events;

use Log;
use App\UserMeta;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OhlcEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $broadcastQueue = 'ohlc';
    /**
     * Create a new event instance.
     *
     * @return void
     */
     public $data;

     public function __construct($h)
     {
        $this->data = $h;
        $this->data->load(['pair'=>function($p){$p->with(['from','to']);}]);
        // echo 'New histo event should be broadcasted: '.json_encode($this->data) . "\n";
     }

     public function  broadcastAs()
     {
         return 'ohlc';
     }

     public  function broadcastWith()
     {
        $this->data->load([
            'source',
            'pair'=>function($q){$q->with(['from','to']);}
        ]);
        $data = $this->data->toArray();
        $data["date"]=date('Y-m-d H:i:s',$data['time']);
        // $data["time"]-=3*60*60;
        $except = UserMeta::where('meta_name','user_tune_corida_#'.$this->data->instrument_id)->pluck('user_id')->toArray();
        // if(count($except))Log::debug('ohlc except tuned guys: '.json_encode($except));
        return ['data' => $data, 'user_id' => $except, 'type' => 'except'];
     }

     /**
      * Get the channels the event should broadcast on.
      *
      * @return \Illuminate\Broadcasting\Channel|array
      */
     public function broadcastOn()
     {
         return new PrivateChannel('channel-private');
     }
}
