<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RealtimeDriverTestEvent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(config('filament-realtime-driver.channel'));
    }

    public function broadcastAs(): string
    {
        return 'event.example';
    }

    public function broadcastWith(): array
    {
        return ['message' => $this->message];
    }
}
