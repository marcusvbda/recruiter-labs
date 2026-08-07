<div class="flex flex-col gap-6" data-state="completed">
    <div
        class="flex flex-col items-center gap-3 rounded-xl border border-success-200 bg-success-50 px-6 py-8 text-center dark:border-success-800 dark:bg-success-950/30">
        <x-filament::icon :icon="$analysis['icon']" class="size-8 text-success-600 dark:text-success-400" />

        @if ($analysis['score'] !== null)
            <p class="text-4xl font-bold text-gray-950 dark:text-white">{{ $analysis['score'] }}<span
                    class="text-lg font-medium text-gray-500 dark:text-gray-400">/100</span></p>
            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                {{ __('applications.admin.ai.overall_score_label') }}</p>
        @endif

        @if ($analysis['analyzed_at'])
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.analyzed_at', ['date' => $analysis['analyzed_at']]) }}
            </p>
        @endif
    </div>

    <div class="flex flex-col gap-3">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ __('applications.admin.ai.criteria_heading') }}</h3>

        @forelse ($analysis['criteria'] as $criterionScore)
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                    <p class="text-sm font-medium text-gray-950 dark:text-white max-w-full md:max-w-9/12">
                        {{ $criterionScore['criterion'] }}</p>
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                            <x-filament::icon icon="heroicon-o-shield-check" class="size-3.5" />
                            {{ __('applications.admin.ai.confidence_label') }}
                            {{ __("applications.admin.ai.confidence.{$criterionScore['confidence']}") }}
                        </span>
                        <x-filament::badge :color="$criterionScore['score'] >= 70
                            ? 'success'
                            : ($criterionScore['score'] >= 40
                                ? 'warning'
                                : 'danger')">
                            {{ $criterionScore['score'] }}/100
                        </x-filament::badge>
                    </div>
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-full rounded-full {{ $criterionScore['score'] >= 70 ? 'bg-success-500' : ($criterionScore['score'] >= 40 ? 'bg-warning-500' : 'bg-danger-500') }}"
                        style="width: {{ $criterionScore['score'] }}%"></div>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterionScore['reason'] }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('applications.admin.ai.no_criteria_scored') }}
            </p>
        @endforelse
    </div>
</div>
