<?php
namespace Vsb\Events;

use Illuminate\Support\Facades\Auth;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;


class DealEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */

    public $deal;
    public $usersId;


    public function __construct($deal, array $usersId)
    {
        $this->deal = $deal;
        $this->usersId = $usersId;
    }

    public function broadcastOn()
    {
        return ['channel-xcryptex'];
    }

    public  function broadcastWith()
    {
        return ['data' => $this->deal, 'user_id' => $this->usersId, 'type' => 'group'];
    }

    public function  broadcastAs()
    {
        return 'presister';
    }
}
