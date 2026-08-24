<div class="flex flex-col gap-6" data-state="completed">
    <div class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-gray-50 px-5 py-5 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                <x-filament::icon :icon="$analysis['icon']" class="size-5" />
            </div>
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $analysis['title'] }}</p>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.ai.assisted_label') }}
                    @if ($analysis['analyzed_at'])
                        · {{ __('applications.admin.ai.analyzed_at', ['date' => $analysis['analyzed_at']]) }}
                    @endif
                </p>
            </div>
        </div>

        {{-- Fit and evidence coverage answer two different questions and are
             never merged into one number: a candidate can match everything that
             could be checked while most of the profile is still unknown. --}}
        <div class="flex flex-wrap gap-x-8 gap-y-3 sm:justify-end">
            @if ($analysis['score'] !== null)
                <div class="sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('applications.admin.ai.overall_score_label') }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 tabular-nums dark:text-white">
                        {{ $analysis['score'] }}<span class="text-sm font-medium text-gray-500 dark:text-gray-400">/100</span>
                    </p>
                </div>
            @endif

            @if ($analysis['coverage'] !== null)
                <div class="sm:text-right">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('applications.admin.ai.coverage_label') }}
                    </p>
                    <p class="mt-1 text-2xl font-semibold text-gray-950 tabular-nums dark:text-white">
                        {{ $analysis['coverage'] }}<span class="text-sm font-medium text-gray-500 dark:text-gray-400">%</span>
                    </p>
                </div>
            @endif
        </div>
    </div>

    <p class="text-xs leading-5 text-gray-500 dark:text-gray-400">
        {{ __('applications.admin.ai.scope_disclosure') }}
    </p>

    <div class="flex flex-col gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('applications.admin.ai.criteria_heading') }}</h3>
            <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                <span class="rounded-full bg-warning-50 px-2.5 py-1 dark:bg-warning-950/30">
                    {{ trans_choice('applications.admin.ai.criteria.needs_validation_count', $analysis['criteria']['needs_validation_count'], ['count' => $analysis['criteria']['needs_validation_count']]) }}
                </span>
                @if ($analysis['criteria']['unassessed_count'] > 0)
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 dark:bg-white/10">
                        {{ trans_choice('applications.admin.ai.criteria.unassessed_count', $analysis['criteria']['unassessed_count'], ['count' => $analysis['criteria']['unassessed_count']]) }}
                    </span>
                @endif
                <span class="rounded-full bg-gray-100 px-2.5 py-1 dark:bg-white/10">
                    {{ trans_choice('applications.admin.ai.criteria.supported_count', $analysis['criteria']['supported_count'], ['count' => $analysis['criteria']['supported_count']]) }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.criteria.needs_validation_heading') }}</h4>
            @forelse ($analysis['criteria']['needs_validation'] as $criterionScore)
                @include('filament.resources.applications.components.criterion-result', [
                    'criterionScore' => $criterionScore,
                    'confidenceColor' => 'warning',
                    'confidenceIcon' => 'heroicon-o-shield-exclamation',
                ])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('applications.admin.ai.criteria.no_needs_validation') }}</p>
            @endforelse
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.criteria.supported_heading') }}</h4>
            @forelse ($analysis['criteria']['supported'] as $criterionScore)
                @include('filament.resources.applications.components.criterion-result', [
                    'criterionScore' => $criterionScore,
                    'confidenceColor' => 'success',
                    'confidenceIcon' => 'heroicon-o-shield-check',
                ])
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('applications.admin.ai.criteria.no_supported') }}</p>
            @endforelse
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('applications.admin.ai.interview_brief.heading') }}</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.interview_brief.description') }}</p>
        </div>

        @forelse ($analysis['interview_brief_items'] as $briefItem)
            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $briefItem['criterion'] }}</p>
                    <x-filament::badge :color="$briefItem['priority'] === 'high'
                        ? 'danger'
                        : ($briefItem['priority'] === 'medium' ? 'warning' : 'gray')">
                        {{ __('applications.admin.ai.interview_brief.priority_label') }}
                        {{ __("applications.admin.ai.interview_brief.priority.{$briefItem['priority']}") }}
                    </x-filament::badge>
                </div>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.ai.interview_brief.reason_label') }}</p>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $briefItem['reason'] }}</p>
                <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.ai.interview_brief.question_label') }}</p>
                <p class="mt-1 text-sm font-medium leading-6 text-gray-950 dark:text-white">{{ $briefItem['question'] }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.interview_brief.empty') }}</p>
        @endforelse
    </div>
</div>
