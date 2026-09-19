<?php

namespace RecruiterLabs\FilamentRealtimeDriver;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentRealtimeDriverPlugin implements Plugin
{
    protected RealtimeConnection $socket;

    /** @var callable|null */
    protected $init = null;

    protected ?string $server = null;

    protected array $auth = [];

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-realtime-driver';
    }

    public function socket(?callable $init = null, ?string $server = null, array $auth = []): static
    {
        $this->init = $init;
        $this->server = $server;
        $this->auth = $auth;

        return $this;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * Connect to the realtime server, run the registered watch() callbacks
     * through the init callback, and block listening for events.
     *
     * This must only run inside a long-lived process (the
     * filament-realtime-driver:listen console command), never during an
     * HTTP request or a one-off artisan command — it never returns while
     * connected.
     */
    public function connectAndListen(): void
    {
        if ($this->init === null) {
            return;
        }

        $server = $this->server ?? config('filament-realtime-driver.server');
        $auth = $this->auth + ['key' => config('filament-realtime-driver.key')];

        $this->socket = new RealtimeConnection();
        $this->socket->connect($server, $auth);

        if ($channel = config('filament-realtime-driver.channel')) {
            $this->socket->subscribe($channel);
        }

        ($this->init)($this->socket);

        $this->socket->listen();
    }
}
