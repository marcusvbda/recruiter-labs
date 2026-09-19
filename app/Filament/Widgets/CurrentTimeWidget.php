<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class CurrentTimeWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Current time', now()->format('H:i:s')),
        ];
    }

    #[On('echo:filament-realtime-driver,event.example')]
    public function refreshAction($payload): void
    {
        $this->dispatch('$refresh');
    }
}
