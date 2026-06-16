<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $resource, public ?int $id = null)
    {
    }

    public function broadcastOn(): Channel
    {
        return new Channel('public-updates');
    }

    public function broadcastAs(): string
    {
        return 'realtime.updated';
    }
}
