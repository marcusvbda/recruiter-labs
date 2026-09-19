<?php

namespace RecruiterLabs\FilamentRealtimeDriver;

use Illuminate\Support\ServiceProvider;
use RecruiterLabs\FilamentRealtimeDriver\Console\ListenCommand;

class FilamentRealtimeDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-realtime-driver.php', 'filament-realtime-driver');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/filament-realtime-driver.php' => config_path('filament-realtime-driver.php'),
        ], 'filament-realtime-driver-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListenCommand::class,
            ]);
        }
    }
}
