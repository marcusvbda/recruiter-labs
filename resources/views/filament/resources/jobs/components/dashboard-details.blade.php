<div class="grid h-full gap-4">
    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex items-start gap-4">
            <span @class([
                'flex size-12 shrink-0 items-center justify-center rounded-2xl',
                'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' => $dashboard['hired_count'] > 0,
                'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400' => $dashboard['hired_count'] === 0,
            ])>
                <x-filament::icon icon="heroicon-o-check-badge" class="size-7" />
            </span>
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('jobs.dashboard.hiring_result') }}
                </p>
                <p class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">
                    @if ($dashboard['hired_count'] > 0)
                        {{ trans_choice('jobs.dashboard.hired_count', $dashboard['hired_count'], ['count' => $dashboard['hired_count']]) }}
                    @else
                        {{ __('jobs.dashboard.no_hires') }}
                    @endif
                </p>
                <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ __('jobs.dashboard.hiring_result_description') }}
                </p>
            </div>
        </div>
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-6">
        <div class="flex items-start gap-4">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                <x-filament::icon icon="heroicon-o-sparkles" class="size-7" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ __('jobs.fields.campaign_expectation') }}
                </p>
                <p class="mt-2 text-sm leading-6 text-gray-700 dark:text-gray-300">
                    {{ filled($job->campaign_expectation) ? $job->campaign_expectation : __('jobs.dashboard.no_expectation') }}
                </p>
            </div>
        </div>
    </section>
</div>
