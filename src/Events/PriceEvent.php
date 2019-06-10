<?php

namespace Vsb\Events;

use Log;
use App\UserMeta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


// class PriceEvent implements ShouldBroadcast
class PriceEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $price;

    public function __construct($price)
    {
        $price->load(['pair'=>function($p){
            $p->with(['from','to']);
        },'source']);
        $this->price = $price;
        // echo 'New price event should be broadcasted: '.json_encode($this->price) . "\n";
    }

    public function  broadcastAs()
    {
        return 'price';
    }

    public  function broadcastWith()
    {
        $data = $this->price->toArray();
        // $data["time"]-=3*60*60;
        $except = UserMeta::where('meta_name','user_tune_corida_#'.$this->price->instrument_id)->pluck('user_id')->toArray();
        // if(count($except))Log::debug('price except tuned guys: '.json_encode($except));
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
