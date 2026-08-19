<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <x-filament::section
            :heading="__('applications.admin.summary.where_heading')"
            icon="heroicon-o-map-pin"
        >
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 text-lg font-semibold text-gray-950 dark:text-white">
                    <span class="size-2.5 rounded-full" style="background-color: {{ $summary['stage']['color'] }}"></span>
                    {{ $summary['stage']['name'] }}
                </span>

                @if ($summary['stage']['position'])
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('applications.admin.summary.stage_position', [
                            'position' => $summary['stage']['position'],
                            'total' => $summary['stage']['total'],
                        ]) }}
                    </span>
                @endif

                @if ($summary['stage']['role'] === 'hired')
                    <x-filament::badge color="success" size="sm">{{ __('statuses.badges.hired') }}</x-filament::badge>
                @elseif ($summary['stage']['role'] === 'closed')
                    <x-filament::badge color="danger" size="sm">{{ __('statuses.badges.closed') }}</x-filament::badge>
                @elseif ($summary['stage']['role'] === 'final_stage')
                    <x-filament::badge color="warning" size="sm">{{ __('statuses.badges.final_stage') }}</x-filament::badge>
                @endif
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-x-1 gap-y-1 text-sm">
                @foreach ($summary['stage']['flow'] as $stage)
                    @if (! $loop->first)
                        <span class="text-gray-300 dark:text-gray-600">&rarr;</span>
                    @endif
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 whitespace-nowrap',
                        'bg-gray-100 font-semibold text-gray-950 dark:bg-white/10 dark:text-white' => $stage['is_current'],
                        'text-gray-500 dark:text-gray-400' => ! $stage['is_current'],
                    ])>
                        <span class="inline-block size-2 rounded-full" style="background-color: {{ $stage['color'] }}"></span>
                        {{ $stage['name'] }}
                    </span>
                @endforeach
            </div>

            <p class="mt-5 border-t border-gray-200 pt-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                {{ __('applications.admin.fields.applied_at') }}: {{ $summary['applied_at'] }}
            </p>
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.summary.next_action_heading')"
            icon="heroicon-o-flag"
        >
            <p class="text-base font-medium text-gray-950 dark:text-white">
                {{ __('applications.admin.summary.next_actions.' . $summary['next_action'] . '.title') }}
            </p>
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.summary.next_actions.' . $summary['next_action'] . '.description') }}
            </p>
        </x-filament::section>
    </div>

    <div class="space-y-6">
        <x-filament::section
            :heading="__('applications.admin.summary.fit_heading')"
            icon="heroicon-o-clipboard-document-check"
        >
            @if ($summary['fit']['score'] !== null)
                <p class="text-3xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                    {{ $summary['fit']['score'] }}<span class="text-lg font-medium text-gray-400">/100</span>
                </p>
                <ul class="mt-4 space-y-2 text-sm">
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.summary.needs_validation_label') }}</span>
                        <span class="font-semibold text-gray-950 tabular-nums dark:text-white">{{ $summary['fit']['needs_validation_count'] }}</span>
                    </li>
                    <li class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">{{ __('applications.admin.summary.established_evidence_label') }}</span>
                        <span class="font-semibold text-gray-950 tabular-nums dark:text-white">{{ $summary['fit']['established_evidence_count'] }}</span>
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
