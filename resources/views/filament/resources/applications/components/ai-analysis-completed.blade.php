<div class="flex flex-col gap-6" data-state="completed">
    <div
        class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-6 py-6 text-center dark:border-white/10 dark:bg-white/5">
        <x-filament::icon :icon="$analysis['icon']" class="size-6 text-gray-500 dark:text-gray-400" />

        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
            {{ __('applications.admin.ai.assisted_label') }}</p>

        @if ($analysis['score'] !== null)
            <p class="text-5xl font-bold {{ $analysis['score'] >= 70 ? 'text-success-600 dark:text-success-400' : ($analysis['score'] >= 40 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}">{{ $analysis['score'] }}<span
                    class="text-sm font-medium text-gray-500 dark:text-gray-400">/100</span></p>
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
        <div>
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                {{ __('applications.admin.ai.criteria_heading') }}</h3>
            <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium text-gray-600 dark:text-gray-300">
                <span class="rounded-full bg-warning-50 px-2.5 py-1 dark:bg-warning-950/30">
                    {{ trans_choice('applications.admin.ai.criteria.needs_validation_count', $analysis['criteria']['needs_validation_count'], ['count' => $analysis['criteria']['needs_validation_count']]) }}
                </span>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 dark:bg-white/10">
                    {{ trans_choice('applications.admin.ai.criteria.established_evidence_count', $analysis['criteria']['established_evidence_count'], ['count' => $analysis['criteria']['established_evidence_count']]) }}
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.criteria.needs_validation_heading') }}</h4>
            @forelse ($analysis['criteria']['needs_validation'] as $criterionScore)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $criterionScore['criterion'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                <span>{{ __('applications.admin.ai.criteria.weight_label', ['weight' => $criterionScore['weight']]) }}</span>
                                <span>·</span>
                                <span>{{ __('applications.admin.ai.criteria.importance_label') }} {{ __("applications.admin.ai.criteria.importance.{$criterionScore['importance']}") }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="gray">{{ __('applications.admin.ai.criteria.fit_label', ['score' => $criterionScore['score']]) }}</x-filament::badge>
                            <x-filament::badge color="warning" icon="heroicon-o-shield-exclamation">
                                {{ __('applications.admin.ai.confidence_label') }} {{ __("applications.admin.ai.confidence.{$criterionScore['confidence']}") }}
                            </x-filament::badge>
                        </div>
                    </div>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-gray-500 dark:bg-gray-400" style="width: {{ $criterionScore['score'] }}%"></div>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterionScore['reason'] }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('applications.admin.ai.criteria.no_needs_validation') }}</p>
            @endforelse
        </div>

        <div class="flex flex-col gap-3">
            <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.ai.criteria.established_evidence_heading') }}</h4>
            @forelse ($analysis['criteria']['established_evidence'] as $criterionScore)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $criterionScore['criterion'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                                <span>{{ __('applications.admin.ai.criteria.weight_label', ['weight' => $criterionScore['weight']]) }}</span>
                                <span>·</span>
                                <span>{{ __('applications.admin.ai.criteria.importance_label') }} {{ __("applications.admin.ai.criteria.importance.{$criterionScore['importance']}") }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge color="gray">{{ __('applications.admin.ai.criteria.fit_label', ['score' => $criterionScore['score']]) }}</x-filament::badge>
                            <x-filament::badge color="success" icon="heroicon-o-shield-check">
                                {{ __('applications.admin.ai.confidence_label') }} {{ __("applications.admin.ai.confidence.{$criterionScore['confidence']}") }}
                            </x-filament::badge>
                        </div>
                    </div>
                    <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-full rounded-full bg-gray-500 dark:bg-gray-400" style="width: {{ $criterionScore['score'] }}%"></div>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterionScore['reason'] }}</p>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('applications.admin.ai.criteria.no_established_evidence') }}</p>
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
