{{--
    One composed operational surface, not a stack of widgets. Attention leads,
    the recruiter's own agenda sits beside it as personal context, the live
    hiring processes follow, and the totals are a single quiet line.

    Every value here is prepared by App\Filament\Pages\Dashboard from the
    services that own its meaning — this file only composes.
--}}
<x-filament-panels::page>
    <div class="rl-overview">
        @if ($summary !== [])
            <p class="rl-overview-summary">
                @foreach ($summary as $figure)
                    @if (! $loop->first)
                        <span class="rl-overview-summary__separator" aria-hidden="true">&middot;</span>
                    @endif

                    <a href="{{ $figure['url'] }}" wire:navigate class="rl-overview-summary__figure">
                        <span class="rl-overview-summary__value">{{ $figure['value'] }}</span>
                        {{ trans_choice('dashboard.summary.' . $figure['key'], $figure['value']) }}
                    </a>
                @endforeach
            </p>
        @endif

        <div class="grid items-start gap-5 lg:grid-cols-3">
            {{-- What needs my attention? The reason the page exists, so it gets
                 the width and sits first in the reading order on every size. --}}
            <section class="rl-overview-panel lg:col-span-2" aria-labelledby="rl-overview-attention-heading">
                <div class="rl-overview-panel__head">
                    <h2 id="rl-overview-attention-heading" class="rl-overview-panel__title">
                        {{ __('attention.heading') }}

                        @if ($attention_total > 0)
                            <span class="rl-overview-panel__count">{{ $attention_total }}</span>
                        @endif
                    </h2>
                </div>

                @if ($attention === [])
                    {{-- A clear queue is information too, and it should cost the
                         page almost no vertical space. --}}
                    <p class="rl-overview-empty">
                        <x-filament::icon icon="heroicon-m-check-circle" class="rl-overview-empty__icon" data-tone="success" aria-hidden="true" />
                        <span>
                            <span class="rl-overview-empty__title">{{ __('attention.empty_heading') }}</span>
                            <span class="rl-overview-empty__text">{{ __('attention.empty_description') }}</span>
                        </span>
                    </p>
                @else
                    <ul class="rl-attention-list">
                        @foreach ($attention as $item)
                            <li class="rl-attention-item" data-severity="{{ $item['severity'] }}">
                                <x-filament::icon :icon="$item['icon']" class="rl-attention-item__icon" aria-hidden="true" />

                                <span class="min-w-0 flex-1">
                                    <span class="rl-attention-item__title">
                                        {{-- Severity is carried by the marker's colour, so it is
                                             also stated in words for anyone who cannot see it. --}}
                                        <span class="sr-only">{{ __('attention.severities.' . $item['severity']) }}:</span>
                                        {{ $item['title'] }}
                                    </span>

                                    <span class="rl-attention-item__meta">
                                        @if ($item['context'])
                                            <span class="rl-attention-item__context">{{ $item['context'] }}</span>
                                        @endif
                                        <span>{{ $item['explanation'] }}</span>
                                    </span>
                                </span>

                                <a href="{{ $item['action_url'] }}" wire:navigate class="rl-overview-action rl-attention-item__action">
                                    {{ $item['action_label'] }}
                                    <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($attention_hidden > 0)
                        <p class="rl-overview-panel__note">
                            {{ trans_choice('attention.hidden', $attention_hidden, ['count' => $attention_hidden]) }}
                        </p>
                    @endif
                @endif
            </section>

            {{-- What do I have next? A compact preview of the recruiter's own
                 commitments; the calendar page stays the operational view. --}}
            <section class="rl-overview-panel" aria-labelledby="rl-overview-agenda-heading">
                <div class="rl-overview-panel__head">
                    <h2 id="rl-overview-agenda-heading" class="rl-overview-panel__title">
                        {{ __('dashboard.agenda.heading') }}
                    </h2>

                    <a href="{{ $calendar_url }}" wire:navigate class="rl-overview-action">
                        {{ __('agenda.navigation_label') }}
                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                    </a>
                </div>

                @if ($agenda->isEmpty())
                    <p class="rl-overview-empty">
                        <x-filament::icon icon="heroicon-m-calendar-days" class="rl-overview-empty__icon" aria-hidden="true" />
                        <span>
                            <span class="rl-overview-empty__title">{{ __('dashboard.agenda.empty_heading') }}</span>
                            <span class="rl-overview-empty__text">{{ __('dashboard.agenda.empty_description') }}</span>
                        </span>
                    </p>
                @else
                    <div class="rl-agenda">
                        @foreach ($agenda->days as $day)
                            @php
                                $dayLabel = match (true) {
                                    $day['is_today'] => __('dashboard.agenda.today'),
                                    $day['is_tomorrow'] => __('dashboard.agenda.tomorrow'),
                                    default => $day['date_label'],
                                };
                            @endphp

                            <h3 class="rl-agenda-day">
                                <span>{{ $dayLabel }}</span>

                                @if ($day['is_today'] || $day['is_tomorrow'])
                                    <span class="rl-agenda-day__date">{{ $day['date_label'] }}</span>
                                @endif
                            </h3>

                            <ul class="rl-agenda-list">
                                @foreach ($day['interviews'] as $interview)
                                    <li>
                                        <a href="{{ $interview['url'] }}" wire:navigate class="rl-agenda-item">
                                            <time datetime="{{ $interview['datetime'] }}" class="rl-agenda-item__time">
                                                {{ $interview['time'] }}
                                            </time>

                                            <span class="min-w-0 flex-1">
                                                <span class="rl-agenda-item__name">{{ $interview['candidate'] }}</span>

                                                @if ($interview['job'])
                                                    <span class="rl-agenda-item__meta">{{ $interview['job'] }}</span>
                                                @endif

                                                <span class="rl-agenda-item__rsvp" data-tone="{{ $interview['rsvp_tone'] }}">
                                                    @if ($interview['rsvp_tone'] === 'danger')
                                                        <x-filament::icon icon="heroicon-m-x-circle" class="size-3.5 shrink-0" aria-hidden="true" />
                                                    @elseif ($interview['rsvp_tone'] === 'success')
                                                        <x-filament::icon icon="heroicon-m-check-circle" class="size-3.5 shrink-0" aria-hidden="true" />
                                                    @endif

                                                    {{ $interview['rsvp_label'] }}
                                                </span>
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endforeach
                    </div>

                    {{-- Stated once for the whole agenda, not on every row. --}}
                    <p class="rl-overview-panel__note">
                        {{ __('agenda.timezone_label', ['timezone' => $agenda->timezone]) }}

                        @if ($agenda->hiddenCount() > 0)
                            <span aria-hidden="true">&middot;</span>
                            {{ trans_choice('dashboard.agenda.hidden', $agenda->hiddenCount(), ['count' => $agenda->hiddenCount()]) }}
                        @endif
                    </p>
                @endif
            </section>
        </div>

        {{-- A short, dismissible introduction on an early visit — not a wizard,
             not a tour. It never blocks the product: closing it away or
             navigating elsewhere leaves it undismissed and it simply renders
             again next time, while "Get started" and "Continue later" are the
             two paths that record it as seen (T04) so it stops repeating. --}}
        @if ($show_welcome && $activation instanceof \App\Data\WorkspaceActivationProgress)
            @php
                $welcomeNextStep = $activation->nextStep();
            @endphp

            <div x-data x-init="$nextTick(() => $dispatch('open-modal', { id: 'rl-welcome-modal' }))"></div>

            <x-filament::modal id="rl-welcome-modal" width="md" wire:key="rl-welcome-modal">
                <x-slot name="heading">
                    {{ __('onboarding.welcome.heading') }}
                </x-slot>

                <div class="rl-welcome">
                    <p class="rl-welcome__intro">{{ __('onboarding.welcome.intro') }}</p>

                    <p class="rl-welcome__progress">
                        {{ __('onboarding.welcome.progress', ['done' => $activation->completedCount(), 'total' => $activation->totalCount()]) }}
                    </p>

                    @if ($welcomeNextStep)
                        <p class="rl-welcome__next">
                            <span class="rl-welcome__next-label">{{ __('onboarding.welcome.next_step_label') }}</span>
                            <span class="rl-welcome__next-title">
                                {{ __('onboarding.checklist.steps.' . $welcomeNextStep['key'] . '.title') }}
                            </span>
                        </p>
                    @endif
                </div>

                <x-slot name="footerActions">
                    <x-filament::button wire:click="startOnboardingWelcome" wire:loading.attr="disabled">
                        {{ __('onboarding.welcome.get_started') }}
                    </x-filament::button>

                    <x-filament::button color="gray" wire:click="dismissOnboardingWelcome" wire:loading.attr="disabled">
                        {{ __('onboarding.welcome.continue_later') }}
                    </x-filament::button>
                </x-slot>
            </x-filament::modal>
        @endif

        {{-- Where is a new workspace in reaching its first evaluation? Attention
             and the recruiter's agenda already led the page above; this stays a
             single compact panel and disappears entirely once activated, so it
             never competes with live recruitment work for space. --}}
        @if ($activation instanceof \App\Data\WorkspaceActivationProgress && ! $activation->isActivated())
            @php
                $nextStepKey = $activation->nextStep()['key'] ?? null;
            @endphp

            <section class="rl-overview-panel" aria-labelledby="rl-overview-activation-heading">
                <div class="rl-overview-panel__head">
                    <h2 id="rl-overview-activation-heading" class="rl-overview-panel__title">
                        {{ __('onboarding.checklist.heading') }}

                        <span class="rl-overview-panel__count" aria-hidden="true">
                            {{ $activation->completedCount() }}/{{ $activation->totalCount() }}
                        </span>
                        <span class="sr-only">
                            {{ __('onboarding.checklist.progress', ['done' => $activation->completedCount(), 'total' => $activation->totalCount()]) }}
                        </span>
                    </h2>

                    @if ($activation->isSetupComplete())
                        <x-filament::badge color="success" size="sm">
                            {{ __('onboarding.checklist.setup_complete') }}
                        </x-filament::badge>
                    @endif
                </div>

                <ul class="rl-activation-list">
                    @foreach ($activation->primarySteps as $step)
                        <li class="rl-activation-item" data-complete="{{ $step['is_complete'] ? 'true' : 'false' }}">
                            <x-filament::icon
                                :icon="$step['is_complete'] ? 'heroicon-m-check-circle' : 'heroicon-o-check-circle'"
                                class="rl-activation-item__icon"
                                aria-hidden="true"
                            />

                            <span class="min-w-0 flex-1">
                                <span class="rl-activation-item__title">
                                    {{ __('onboarding.checklist.steps.' . $step['key'] . '.title') }}
                                </span>

                                @if ($step['key'] === $nextStepKey)
                                    <span class="rl-activation-item__hint">
                                        {{ __('onboarding.checklist.steps.' . $step['key'] . '.hint') }}
                                    </span>
                                @endif
                            </span>

                            @if ($step['url'])
                                <a
                                    href="{{ $step['url'] }}"
                                    wire:navigate
                                    class="rl-overview-action rl-activation-item__action"
                                    @class(['rl-activation-item__action--primary' => $step['key'] === $nextStepKey])
                                >
                                    {{ __('onboarding.checklist.steps.' . $step['key'] . '.action') }}
                                    <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>

                {{-- Optional setup stays visually apart from the primary journey and
                     is never counted in its progress. A step is a link only when
                     the current user is actually authorized for the underlying
                     action — never a control that will refuse them. --}}
                <div class="rl-activation-optional">
                    <p class="rl-activation-optional__heading">{{ __('onboarding.checklist.optional_heading') }}</p>

                    <ul class="rl-activation-optional-list">
                        @foreach ($activation->optionalSteps as $optional)
                            <li class="rl-activation-optional-item">
                                <span class="rl-activation-optional-item__label">
                                    {{ __('onboarding.checklist.optional.' . $optional['key'] . '.title') }}
                                </span>

                                @if ($optional['is_done'])
                                    <span class="rl-activation-optional-item__state" data-tone="success">
                                        <x-filament::icon icon="heroicon-m-check-circle" class="size-3.5 shrink-0" aria-hidden="true" />
                                        {{ __('onboarding.checklist.optional_done') }}
                                    </span>
                                @elseif ($optional['is_actionable'])
                                    <a href="{{ $optional['url'] }}" wire:navigate class="rl-overview-action">
                                        {{ __('onboarding.checklist.optional.' . $optional['key'] . '.action') }}
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                                    </a>
                                @else
                                    <span class="rl-activation-optional-item__state">
                                        {{ __('onboarding.checklist.optional_not_available') }}
                                    </span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- How is hiring progressing? Scannable without opening every job. --}}
        <section class="rl-overview-panel" aria-labelledby="rl-overview-processes-heading">
            <div class="rl-overview-panel__head">
                <h2 id="rl-overview-processes-heading" class="rl-overview-panel__title">
                    {{ __('dashboard.processes.heading') }}
                </h2>

                <a href="{{ $jobs_url }}" wire:navigate class="rl-overview-action">
                    {{ __('dashboard.processes.view_all') }}
                    <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 shrink-0" aria-hidden="true" />
                </a>
            </div>

            @if ($processes === [])
                <p class="rl-overview-empty">
                    <x-filament::icon icon="heroicon-m-briefcase" class="rl-overview-empty__icon" aria-hidden="true" />
                    <span>
                        <span class="rl-overview-empty__title">{{ __('dashboard.processes.empty_heading') }}</span>
                        <span class="rl-overview-empty__text">{{ __('dashboard.processes.empty_description') }}</span>
                    </span>
                </p>
            @else
                <ul class="rl-process-list">
                    @foreach ($processes as $process)
                        @php
                            $progress = $process['progress'];
                        @endphp

                        <li>
                            <a href="{{ $process['url'] }}" wire:navigate class="rl-process-item">
                                <span class="min-w-0 flex-1">
                                    <span class="rl-process-item__name">{{ $process['name'] }}</span>

                                    {{-- Only the counts that are actually saying something.
                                         Applicants always shows: zero applicants is a signal. --}}
                                    <span class="rl-process-item__metrics">
                                        <span>
                                            <span class="rl-process-item__value">{{ $progress['applications'] }}</span>
                                            {{ trans_choice('jobs.progress.applicants', $progress['applications']) }}
                                        </span>

                                        @if ($progress['interviewing'] > 0)
                                            <span>
                                                <span class="rl-process-item__value">{{ $progress['interviewing'] }}</span>
                                                {{ trans_choice('jobs.progress.interviewing', $progress['interviewing']) }}
                                            </span>
                                        @endif

                                        @if ($progress['finalists'] > 0)
                                            <span>
                                                <span class="rl-process-item__value">{{ $progress['finalists'] }}</span>
                                                {{ trans_choice('jobs.progress.finalists', $progress['finalists']) }}
                                            </span>
                                        @endif
                                    </span>
                                </span>

                                <span class="rl-process-item__state">
                                    {{-- Hires are always read against the job's own target, so
                                         "1 hired" can never be mistaken for "done" on a job that
                                         set out to hire four. --}}
                                    <span class="rl-process-item__hires" @class(['rl-process-item__hires--none' => $progress['hired'] === 0])>
                                        {{ $progress['hired'] }}/{{ $progress['hiring_target'] }}
                                        <span class="rl-process-item__hires-label">{{ __('jobs.progress.hired') }}</span>
                                    </span>

                                    {{-- One state at a time: the most consequential one. --}}
                                    @if ($progress['target_reached'])
                                        <x-filament::badge color="success" size="sm">
                                            {{ __('jobs.progress.target_reached') }}
                                        </x-filament::badge>
                                    @elseif ($progress['waiting'] > 0)
                                        <x-filament::badge color="warning" size="sm">
                                            {{ trans_choice('jobs.progress.waiting_too_long', $progress['waiting'], ['count' => $progress['waiting']]) }}
                                        </x-filament::badge>
                                    @elseif ($process['is_stalled'])
                                        <x-filament::badge color="warning" size="sm">
                                            {{ __('jobs.progress.needs_attention') }}
                                        </x-filament::badge>
                                    @endif

                                    @if ($process['is_paused'])
                                        <x-filament::badge color="gray" size="sm">
                                            {{ __('jobs.state.paused') }}
                                        </x-filament::badge>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                @if ($processes_hidden > 0)
                    <p class="rl-overview-panel__note">
                        {{ trans_choice('dashboard.processes.hidden', $processes_hidden, ['count' => $processes_hidden]) }}
                    </p>
                @endif
            @endif
        </section>
    </div>
</x-filament-panels::page>
