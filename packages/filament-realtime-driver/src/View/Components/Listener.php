<?php

namespace RecruiterLabs\FilamentRealtimeDriver\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Listener extends Component
{
    public string $socketUrl;

    public function __construct(
        public string $channel,
        public string $event,
    ) {
        $server = config('filament-realtime-driver.server');
        $key = config('filament-realtime-driver.key');
        $scheme = config('filament-realtime-driver.secure') ? 'wss' : 'ws';

        $this->socketUrl = "{$scheme}://{$server}/app/{$key}?protocol=7&client=js&version=1.0";
    }

    public function render(): View
    {
        return view('filament-realtime-driver::components.listener');
    }
}
