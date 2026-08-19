<section class="rl-job-summary" aria-label="{{ __('jobs.workspace.summary_label') }}">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
        <x-filament::badge :color="$summary['state_color']">
            {{ $summary['state_label'] }}
        </x-filament::badge>

        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('jobs.fields.pipeline') }}:
            <a href="{{ $summary['pipeline_url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">
                {{ $summary['pipeline_name'] }}
            </a>
        </span>

        <span class="text-xs text-gray-400 dark:text-gray-500">{{ $summary['key'] }}</span>
    </div>

    <dl class="rl-job-summary__metrics">
        @foreach ($summary['metrics'] as $metric)
            <div>
                <dt>{{ $metric['label'] }}</dt>
                <dd @class([
                    'tabular-nums',
                    $metric['color'] => $metric['value'] > 0,
                    'text-gray-400 dark:text-gray-500' => $metric['value'] === 0,
                ])>{{ $metric['value'] }}</dd>
            </div>
        @endforeach
    </dl>
</section>
