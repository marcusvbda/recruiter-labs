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
        {{-- Fit and coverage are shown once in this tab, in the evaluation
             card below this one — not repeated here in a second visual
             language (AC28). This card instead gives direct access to the
             candidate's primary submitted document (AC29). --}}
        <x-filament::section
            :heading="__('applications.admin.summary.document_heading')"
            icon="heroicon-o-document-text"
        >
            @if ($summary['document'])
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $summary['document']['original_name'] }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $summary['document']['type'] }} · {{ $summary['document']['extension'] }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-4">
                    @if ($summary['document']['can_preview'])
                        <x-filament::link :href="$summary['document']['view_url']" target="_blank" rel="noopener noreferrer" icon="heroicon-m-eye" size="sm">
                            {{ __('applications.admin.actions.view_document') }}
                        </x-filament::link>
                    @endif
                    <x-filament::link :href="$summary['document']['download_url']" icon="heroicon-m-arrow-down-tray" icon-position="after" size="sm" color="gray">
                        {{ __('applications.admin.actions.download_document') }}
                    </x-filament::link>
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.empty.documents') }}
                </p>
            @endif
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
