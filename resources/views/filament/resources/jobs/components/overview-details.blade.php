<div class="grid h-full gap-4">
    <section class="rl-settings-panel">
        <div class="flex items-start gap-4">
            <span @class([
                'flex size-12 shrink-0 items-center justify-center rounded-2xl',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $details['hired_count'] > 0,
                'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => $details['hired_count'] === 0,
            ])>
                <x-filament::icon icon="heroicon-o-check-badge" class="size-7" />
            </span>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('jobs.overview.hiring_result') }}
                </p>
                <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                    @if ($details['hired_count'] > 0)
                        {{ trans_choice('jobs.overview.hired_count', $details['hired_count'], ['count' => $details['hired_count']]) }}
                    @else
                        {{ __('jobs.overview.no_hires') }}
                    @endif
                </p>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ __('jobs.overview.hiring_result_description') }}
                </p>
            </div>
        </div>
    </section>

    <section class="rl-settings-panel">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('jobs.overview.workflow') }}
                </p>
                <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                    {{ $details['pipeline_name'] }}
                </p>
            </div>
            <x-filament::link :href="$details['pipeline_url']" icon="heroicon-m-cog-6-tooth" size="sm" color="gray">
                {{ __('jobs.overview.configure_workflow') }}
            </x-filament::link>
        </div>

        <ul class="mt-4 space-y-2">
            @foreach ($details['stages'] as $stage)
                <li class="flex items-center justify-between gap-3 text-sm">
                    <span class="flex min-w-0 items-center gap-2">
                        <span class="size-2 shrink-0 rounded-full" style="background-color: {{ $stage['color'] }}"></span>
                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $stage['name'] }}</span>
                        @if ($stage['is_hired'])
                            <x-filament::badge color="success" size="sm">{{ __('statuses.badges.hired') }}</x-filament::badge>
                        @elseif ($stage['is_final_stage'])
                            <x-filament::badge color="warning" size="sm">{{ __('statuses.badges.final_stage') }}</x-filament::badge>
                        @endif
                    </span>
                    <span @class([
                        'shrink-0 font-semibold tabular-nums',
                        'text-gray-950 dark:text-white' => $stage['count'] > 0,
                        'text-gray-400 dark:text-gray-500' => $stage['count'] === 0,
                    ])>{{ $stage['count'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
