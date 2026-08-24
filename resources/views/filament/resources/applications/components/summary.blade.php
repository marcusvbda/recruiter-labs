<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <x-filament::section
            :heading="__('applications.admin.summary.where_heading')"
            icon="heroicon-o-map-pin"
        >
            {{-- The current stage, not a position in a sequence: "Hired" and
                 "Rejected" are alternative outcomes, so numbering the stages
                 would describe a path candidates do not actually walk. --}}
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 text-lg font-semibold text-gray-950 dark:text-white">
                    <span class="size-2.5 rounded-full" style="background-color: {{ $summary['stage']['color'] }}"></span>
                    {{ $summary['stage']['name'] }}
                </span>

                @if ($summary['stage']['role'] === 'hired')
                    <x-filament::badge color="success" size="sm">{{ __('statuses.badges.hired') }}</x-filament::badge>
                @elseif ($summary['stage']['role'] === 'closed')
                    <x-filament::badge color="danger" size="sm">{{ __('statuses.badges.closed') }}</x-filament::badge>
                @elseif ($summary['stage']['role'] === 'final_stage')
                    <x-filament::badge color="warning" size="sm">{{ __('statuses.badges.final_stage') }}</x-filament::badge>
                @endif

                @if ($summary['stage']['is_overdue'])
                    <x-filament::badge color="warning" size="sm" icon="heroicon-m-clock">
                        {{ __('applications.pipeline.kanban.waiting_too_long') }}
                    </x-filament::badge>
                @endif
            </div>

            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.summary.stage_age', [
                    'age' => $summary['stage']['age'],
                    'stage' => $summary['stage']['name'],
                ]) }}
                @if ($summary['stage']['threshold'])
                    <span class="text-gray-400 dark:text-gray-500">
                        · {{ __('applications.admin.summary.stage_threshold', ['threshold' => $summary['stage']['threshold']]) }}
                    </span>
                @endif
            </p>

            <dl class="mt-5 grid gap-x-6 gap-y-2 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 dark:border-white/10">
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.fields.applied_at') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $summary['applied_at'] }}</dd>
                </div>
                <div class="flex justify-between gap-3 sm:block">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.summary.stage_entered_at') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $summary['stage']['entered_at'] }}</dd>
                </div>
            </dl>
        </x-filament::section>
    </div>

    <div class="space-y-6">
        <x-filament::section
            :heading="__('applications.admin.summary.fit_heading')"
            icon="heroicon-o-clipboard-document-check"
        >
            @if ($summary['fit']['score'] !== null || $summary['fit']['coverage'] !== null)
                {{-- Fit and evidence coverage stand side by side, never combined:
                     one says how well the assessable criteria were matched, the
                     other how much could be assessed at all. --}}
                <div class="flex flex-wrap gap-x-8 gap-y-3">
                    @if ($summary['fit']['score'] !== null)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('applications.admin.ai.overall_score_label') }}
                            </p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                                {{ $summary['fit']['score'] }}<span class="text-lg font-medium text-gray-400">/100</span>
                            </p>
                        </div>
                    @endif

                    @if ($summary['fit']['coverage'] !== null)
                        <div>
                            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                {{ __('applications.admin.ai.coverage_label') }}
                            </p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                                {{ $summary['fit']['coverage'] }}<span class="text-lg font-medium text-gray-400">%</span>
                            </p>
                        </div>
                    @endif
                </div>

                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.summary.needs_validation_label') }}</span>
                        <span class="font-semibold text-gray-950 tabular-nums dark:text-white">{{ $summary['fit']['needs_validation_count'] }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.summary.supported_label') }}</span>
                        <span class="font-semibold text-gray-950 tabular-nums dark:text-white">{{ $summary['fit']['supported_count'] }}</span>
                    </li>
                </ul>
                <p class="mt-4 text-xs leading-5 text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.summary.fit_disclaimer') }}
                </p>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $summary['fit']['label'] }}
                </p>
            @endif

            <div class="mt-4">
                <x-filament::link :href="$summary['fit']['url']" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
                    {{ __('applications.admin.tabs.evaluation') }}
                </x-filament::link>
            </div>
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.summary.interview_heading')"
            icon="heroicon-o-calendar-days"
        >
            @if ($summary['interview'])
                <p class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ $summary['interview']['scheduled_at'] }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $summary['interview']['timezone'] }} · {{ $summary['interview']['rsvp'] }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-4">
                    @if ($summary['interview']['meeting_url'])
                        <x-filament::link :href="$summary['interview']['meeting_url']" target="_blank" rel="noopener noreferrer" icon="heroicon-m-video-camera" size="sm">
                            {{ __('applications.admin.actions.join_meet') }}
                        </x-filament::link>
                    @endif
                    <x-filament::link :href="$summary['interview']['url']" icon="heroicon-m-arrow-right" icon-position="after" size="sm" color="gray">
                        {{ __('applications.admin.tabs.interviews') }}
                    </x-filament::link>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.summary.no_interview') }}
                </p>
            @endif
        </x-filament::section>
    </div>
</div>
