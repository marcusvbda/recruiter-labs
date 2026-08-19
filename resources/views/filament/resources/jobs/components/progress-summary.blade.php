@php
    $metrics = [
        ['key' => 'applicants', 'value' => $progress['applications'], 'color' => 'text-gray-950 dark:text-white'],
        ['key' => 'interviewing', 'value' => $progress['interviewing'], 'color' => 'text-info-600 dark:text-info-400'],
        ['key' => 'finalists', 'value' => $progress['finalists'], 'color' => 'text-warning-600 dark:text-warning-400'],
        ['key' => 'hired', 'value' => $progress['hired'], 'color' => 'text-success-600 dark:text-success-400'],
    ];
@endphp

<span class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
    @foreach ($metrics as $metric)
        <span class="whitespace-nowrap">
            <span @class(['font-semibold tabular-nums', $metric['color'] => $metric['value'] > 0, 'text-gray-400 dark:text-gray-500' => $metric['value'] === 0])>
                {{ $metric['value'] }}
            </span>
            <span class="text-gray-500 dark:text-gray-400">
                {{ trans_choice('jobs.progress.' . $metric['key'], $metric['value'], ['count' => $metric['value']]) }}
            </span>
        </span>
    @endforeach

    @if ($isStalled)
        <x-filament::badge color="warning" size="sm" icon="heroicon-m-exclamation-triangle">
            {{ __('jobs.progress.needs_attention') }}
        </x-filament::badge>
    @endif
</span>
