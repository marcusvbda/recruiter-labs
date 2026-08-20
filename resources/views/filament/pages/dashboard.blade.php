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
