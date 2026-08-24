{{-- Confirmed criteria need one quiet line, not a celebration. --}}
<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
    <x-filament::badge color="success" icon="heroicon-m-check-circle">
        {{ __('jobs.criteria.confirmed.badge') }}
    </x-filament::badge>

    <span>
        @if ($confirmedAt && $confirmedBy)
            {{ __('jobs.criteria.confirmed.by_on', ['name' => $confirmedBy, 'date' => $confirmedAt]) }}
        @elseif ($confirmedAt)
            {{ __('jobs.criteria.confirmed.on', ['date' => $confirmedAt]) }}
        @endif
    </span>

    <span class="text-gray-400 dark:text-gray-500">{{ __('jobs.criteria.confirmed.governs') }}</span>
</div>
