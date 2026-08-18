@php
    $connection = $interviews['connection'];
@endphp

<div class="space-y-6">
    @if (! $connection['is_connected'])
        <x-filament::section
            :heading="__($connection['needs_reauthorization']
                ? 'applications.admin.interviews.calendar_reconnect_heading'
                : 'applications.admin.interviews.calendar_disconnected_heading')"
            :description="__($connection['needs_reauthorization']
                ? 'applications.admin.interviews.calendar_reconnect_description'
                : 'applications.admin.interviews.calendar_disconnected_description')"
            icon="heroicon-o-calendar-days"
        >
            <x-filament::button tag="a" :href="$connection['settings_url']" :color="$connection['needs_reauthorization'] ? 'warning' : 'primary'">
                {{ __($connection['needs_reauthorization']
                    ? 'applications.admin.actions.reconnect_calendar'
                    : 'applications.admin.actions.connect_calendar') }}
            </x-filament::button>
        </x-filament::section>
    @endif

    @foreach (['upcoming', 'past', 'cancelled'] as $section)
        <x-filament::section :heading="__('applications.admin.interviews.'.$section.'_heading')" icon="heroicon-o-calendar-days">
            <div class="space-y-4">
                @forelse ($interviews[$section] as $interview)
                    <article class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 sm:p-5 dark:border-white/10 dark:bg-white/5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-950 dark:text-white">
                                    {{ $interview['scheduled_at'] }} – {{ $interview['ends_at'] }}
                                </p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $interview['timezone'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-filament::badge :color="$interview['status_color']">
                                    {{ __($interview['status_label']) }}
                                </x-filament::badge>
                                <x-filament::badge :color="$interview['sync_status_color']">
                                    {{ __($interview['sync_status_label']) }}
                                </x-filament::badge>
                            </div>
                        </div>

                        @if ($interview['sync_error'])
                            <p class="mt-4 rounded-xl border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:border-danger-400/30 dark:bg-danger-500/10 dark:text-danger-300">
                                {{ __('applications.admin.interviews.sync_failed_description') }}
                            </p>
                        @endif

                        <dl class="mt-4 grid gap-4 border-y border-gray-200 py-4 text-sm sm:grid-cols-2 xl:grid-cols-4 dark:border-white/10">
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ __('applications.admin.interviews.fields.duration') }}</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $interview['duration'] }} {{ __('applications.admin.interviews.minutes_short') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ __('applications.admin.interviews.rsvp.label') }}</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ __('applications.admin.interviews.rsvp.'.$interview['rsvp_status']) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ __('applications.admin.interviews.fields.last_synced_at') }}</dt>
                                <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $interview['last_synced_at'] ?? __('applications.admin.interviews.not_synced') }}</dd>
                            </div>
                            @if ($interview['cancelled_at'])
                                <div>
                                    <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">{{ __('applications.admin.interviews.fields.cancelled_at') }}</dt>
                                    <dd class="mt-1 font-medium text-gray-950 dark:text-white">{{ $interview['cancelled_at'] }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($interview['meeting_url'] || $interview['can_reschedule'] || $interview['can_cancel'] || $interview['can_refresh'])
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if ($interview['meeting_url'])
                                    <x-filament::button tag="a" size="sm" :href="$interview['meeting_url']" target="_blank" rel="noopener noreferrer">
                                        {{ __('applications.admin.actions.join_meet') }}
                                    </x-filament::button>
                                @endif
                                @if ($interview['can_reschedule'])
                                    <x-filament::button size="sm" color="gray" outlined wire:click="mountAction('rescheduleInterview', { interview: {{ $interview['id'] }} })">
                                        {{ __('applications.admin.actions.reschedule_interview') }}
                                    </x-filament::button>
                                @endif
                                @if ($interview['can_cancel'])
                                    <x-filament::button size="sm" color="danger" outlined wire:click="mountAction('cancelInterview', { interview: {{ $interview['id'] }} })">
                                        {{ __('applications.admin.actions.cancel_interview') }}
                                    </x-filament::button>
                                @endif
                                @if ($interview['can_refresh'])
                                    <x-filament::button size="sm" color="gray" outlined wire:click="mountAction('refreshInterview', { interview: {{ $interview['id'] }} })">
                                        {{ __('applications.admin.actions.refresh_interview') }}
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <x-filament::empty-state
                        :contained="false"
                        :heading="__('applications.admin.interviews.empty_'.$section)"
                        icon="heroicon-o-calendar-days"
                        icon-color="gray"
                    />
                @endforelse
            </div>
        </x-filament::section>
    @endforeach

    <x-filament::section
        :heading="__('applications.admin.interviews.brief.heading')"
        :description="__('applications.admin.interviews.brief.description')"
        icon="heroicon-o-document-magnifying-glass"
    >
        <div class="space-y-4">
            @forelse ($interviews['brief_items'] as $briefItem)
                <article class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-400/20 dark:bg-primary-500/10">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-semibold text-gray-950 dark:text-white">{{ $briefItem['criterion'] }}</h3>
                        <x-filament::badge :color="match ($briefItem['priority']) { 'high' => 'danger', 'medium' => 'warning', default => 'gray' }">
                            {{ __('applications.admin.interviews.brief.priority_label') }} {{ __('applications.admin.interviews.brief.priority.'.$briefItem['priority']) }}
                        </x-filament::badge>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">{{ __('applications.admin.interviews.brief.reason_label') }}</span>
                        {{ $briefItem['reason'] }}
                    </p>
                    <p class="mt-3 rounded-lg bg-white/70 p-3 text-sm font-medium text-gray-950 dark:bg-black/10 dark:text-white">
                        <span class="font-semibold">{{ __('applications.admin.interviews.brief.question_label') }}</span>
                        {{ $briefItem['question'] }}
                    </p>
                </article>
            @empty
                <x-filament::empty-state
                    :contained="false"
                    :heading="__('applications.admin.interviews.brief.empty')"
                    icon="heroicon-o-document-magnifying-glass"
                    icon-color="gray"
                />
            @endforelse
        </div>
    </x-filament::section>
</div>
