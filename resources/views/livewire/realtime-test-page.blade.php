<div>
    <h2>Filament Realtime Driver — &lt;x-filament-realtime-driver::listener&gt; demo</h2>
    <p>Hit <a href="{{ route('realtime-test') }}">{{ route('realtime-test') }}</a> in another tab to fire an event.</p>

    <x-filament-realtime-driver::listener channel="filament-realtime-driver" event="event.example"
        callback="console.log('Realtime event',event)">
        <p>Last message: <strong x-text="event.message
        ?? 'waiting for an event…'"></strong></p>

        @script
            <script>
                console.log('Realtime listener mounted', event);
            </script>
        @endscript
    </x-filament-realtime-driver::listener>
</div>
