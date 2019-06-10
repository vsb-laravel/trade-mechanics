<?php

namespace Vsb\Events;

use Log;
use App\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class OhlcTuneEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $broadcastQueue = 'ohlcTune';
    /**
     * Create a new event instance.
     *
     * @return void
     */
    public $ohlc;


    public function __construct($data)
    {
        $this->ohlc = $data;
        $this->ohlc->load(['pair'=>function($p){$p->with(['from','to']);}]);
        // echo "Tune ohlc broadcasting..\n";
        // Log::debug('New tune ohlc event: '.json_encode($data,JSON_PRETTY_PRINT));
    }

    public  function broadcastWith()
    {
        $data = $this->ohlc->toArray();
        $data["date"]=date('Y-m-d H:i:s',$data['time']);
        // $data["time"]-=3*60*60;
        $data["tune"]=true;

        $user = User::find($this->ohlc->user_id);
        $data["user"]=$user;
        $group = $user->parents;
        $group[]=$user->id;
        echo "Tune ohlc broadcasting: ".json_encode($data)."\n" ;
        return ['data' => $data, 'user_id' => $group, 'type' => 'group'];
    }

    public function  broadcastAs()
    {
        return 'ohlc';
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
