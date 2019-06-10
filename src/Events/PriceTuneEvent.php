<?php

namespace Vsb\Events;

use App\Source;
use App\Price;
use Illuminate\Support\Facades\Auth;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


// class PriceTuneEvent implements ShouldBroadcast
class PriceTuneEvent
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
        }]);
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
        $data["source"] = Price::find($data['price_id'])->source();
        return ['data' => $data, 'user_id' => $data["user_id"], 'type' => 'private'];
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
