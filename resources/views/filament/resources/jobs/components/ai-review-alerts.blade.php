<div class="mt-6 flex flex-col gap-3">
    <div>
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('jobs.criteria.review_alerts.heading') }}</h3>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('jobs.criteria.review_alerts.assisted_label') }}</span>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('jobs.criteria.review_alerts.description') }}</p>
    </div>

    @if ($alerts->isNotEmpty())
        @foreach ($alerts as $alert)
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $alert->category }}</p>
                    <x-filament::badge :color="$alert->severity === 'high'
                        ? 'danger'
                        : ($alert->severity === 'medium' ? 'warning' : 'gray')">
                        {{ __('jobs.criteria.review_alerts.severity_label') }}
                        {{ __("jobs.criteria.review_alerts.severity.{$alert->severity}") }}
                    </x-filament::badge>
                </div>
                @if ($alert->excerpt)
                    <p class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm italic text-gray-600 dark:bg-white/5 dark:text-gray-300">
                        “{{ $alert->excerpt }}”
                    </p>
                @endif
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('jobs.criteria.review_alerts.issue_label') }}</p>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $alert->issue }}</p>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('jobs.criteria.review_alerts.suggestion_label') }}</p>
                <p class="mt-1 text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $alert->suggestion }}</p>
            </div>
        @endforeach
    @else
        <p class="rounded-lg border border-gray-200 px-4 py-3 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            {{ __('jobs.criteria.review_alerts.empty') }}</p>
    @endif
</div>
