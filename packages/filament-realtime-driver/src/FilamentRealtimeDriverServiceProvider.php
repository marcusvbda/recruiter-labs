<?php

namespace RecruiterLabs\FilamentRealtimeDriver;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use RecruiterLabs\FilamentRealtimeDriver\Console\ListenCommand;
use RecruiterLabs\FilamentRealtimeDriver\View\Components\Listener;

class FilamentRealtimeDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-realtime-driver.php', 'filament-realtime-driver');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-realtime-driver');

        Blade::componentNamespace('RecruiterLabs\\FilamentRealtimeDriver\\View\\Components', 'filament-realtime-driver');

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
