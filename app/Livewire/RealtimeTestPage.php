<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Demo page for <x-filament-realtime-driver::listener>: `@script` only
 * works inside a real Livewire component, so this exists purely to give the
 * component a Livewire render context to mount into.
 */
#[Layout('layouts.livewire-test')]
class RealtimeTestPage extends Component
{
    public function render(): View
    {
        return view('livewire.realtime-test-page');
    }
}
