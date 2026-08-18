@php
    // The grid draws one pixel per minute, so an hour row is exactly 60px tall
    // and an event's offset/height can be derived straight from its minutes.
    $firstHour = $hours[0] ?? 0;
    $gridHeight = count($hours) * 60;
@endphp

<x-filament-panels::page>
    <div
        class="flex flex-col gap-4"
        @unless ($hasResolvedTimezone)
            x-data
            x-init="$wire.resolveTimezone(Intl.DateTimeFormat().resolvedOptions().timeZone)"
        @endunless
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2">
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-left" icon-only
                    wire:click="previousWeek" :label="__('agenda.actions.previous_week')" />
                <x-filament::button color="gray" size="sm" wire:click="currentWeek">
                    {{ __('agenda.actions.today') }}
                </x-filament::button>
                <x-filament::button color="gray" size="sm" icon="heroicon-m-chevron-right" icon-only
                    wire:click="nextWeek" :label="__('agenda.actions.next_week')" />

                <div class="ml-2">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $week_label }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('agenda.timezone_label', ['timezone' => $timezone]) }}
                    </p>
                </div>
            </div>

            <div class="w-full sm:w-64">
                <select wire:model.live="recruiterFilter"
                    class="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                    @foreach ($recruiters as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if (! $is_calendar_connected)
            <div class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5">
                <div>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">
                        {{ __('agenda.disconnected.heading') }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ __('agenda.disconnected.description') }}</p>
                </div>
                <x-filament::button tag="a" :href="$settings_url" size="sm" color="gray">
                    {{ __('agenda.actions.connect_calendar') }}
                </x-filament::button>
            </div>
        @elseif ($agenda_unavailable)
            <div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 dark:border-warning-400/30 dark:bg-warning-400/10">
                <p class="text-sm font-medium text-warning-800 dark:text-warning-200">
                    {{ __('agenda.unavailable.heading') }}</p>
                <p class="text-xs text-warning-700 dark:text-warning-300">
                    {{ __('agenda.unavailable.description') }}</p>
            </div>
        @elseif (! $shows_own_google_events)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ __('agenda.other_recruiter_notice') }}
            </p>
        @endif

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="min-w-[56rem]">
                {{-- Day headers --}}
                <div class="grid border-b border-gray-200 dark:border-white/10"
                    style="grid-template-columns: 4rem repeat(7, minmax(0, 1fr));">
                    <div class="border-r border-gray-200 dark:border-white/10"></div>
                    @foreach ($days as $day)
                        <div @class([
                            'border-r border-gray-200 px-2 py-2 text-center last:border-r-0 dark:border-white/10',
                            'bg-primary-50 dark:bg-primary-500/10' => $day['is_today'],
                        ])>
                            <p @class([
                                'text-xs font-medium uppercase tracking-wide',
                                'text-primary-600 dark:text-primary-400' => $day['is_today'],
                                'text-gray-500 dark:text-gray-400' => ! $day['is_today'],
                            ])>{{ $day['label'] }}</p>
                            <p @class([
                                'text-sm font-semibold',
                                'text-primary-700 dark:text-primary-300' => $day['is_today'],
                                'text-gray-950 dark:text-white' => ! $day['is_today'],
                            ])>{{ $day['day'] }}</p>
                        </div>
                    @endforeach
                </div>

                {{-- All-day row, only when something occupies it --}}
                @if (collect($all_day_events)->flatten(1)->isNotEmpty())
                    <div class="grid border-b border-gray-200 dark:border-white/10"
                        style="grid-template-columns: 4rem repeat(7, minmax(0, 1fr));">
                        <div class="border-r border-gray-200 px-2 py-2 text-right text-[0.65rem] font-medium uppercase tracking-wide text-gray-400 dark:border-white/10">
                            {{ __('agenda.all_day') }}
                        </div>
                        @foreach ($days as $day)
                            <div class="space-y-1 border-r border-gray-200 p-1 last:border-r-0 dark:border-white/10">
                                @foreach ($all_day_events[$day['date']] ?? [] as $event)
                                    <div class="truncate rounded bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200"
                                        title="{{ $event['title'] }}">
                                        {{ $event['title'] }}
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Hour grid --}}
                <div class="grid" style="grid-template-columns: 4rem repeat(7, minmax(0, 1fr));">
                    {{-- Hour gutter --}}
                    <div class="border-r border-gray-200 dark:border-white/10">
                        @foreach ($hours as $hour)
                            <div class="h-[60px] border-b border-gray-100 pr-2 text-right dark:border-white/5">
                                <span class="text-[0.65rem] text-gray-400">
                                    {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00
                                </span>
                            </div>
                        @endforeach
                    </div>

                    @foreach ($days as $day)
                        <div @class([
                            'relative border-r border-gray-200 last:border-r-0 dark:border-white/10',
                            'bg-primary-50/40 dark:bg-primary-500/5' => $day['is_today'],
                        ]) style="height: {{ $gridHeight }}px;">
                            @foreach ($hours as $hour)
                                <div class="h-[60px] border-b border-gray-100 dark:border-white/5"></div>
                            @endforeach

                            @foreach ($timed_events[$day['date']] ?? [] as $event)
                                @php
                                    $top = max(0, $event['start_minutes'] - $firstHour * 60);
                                    $height = min($event['duration_minutes'], max(20, $gridHeight - $top));
                                    $width = 100 / $event['lane_count'];
                                @endphp

                                <{{ $event['url'] ? 'a' : 'div' }}
                                    @if ($event['url'])
                                        href="{{ $event['url'] }}"
                                        @if ($event['is_interview']) wire:navigate @else target="_blank" rel="noopener noreferrer" @endif
                                    @endif
                                    @class([
                                        'absolute overflow-hidden rounded-md border px-2 py-1 text-xs',
                                        'border-primary-200 bg-primary-100 text-primary-900 hover:bg-primary-200 dark:border-primary-400/30 dark:bg-primary-500/20 dark:text-primary-100' => $event['is_interview'],
                                        'border-gray-200 bg-gray-100 text-gray-700 dark:border-white/10 dark:bg-white/10 dark:text-gray-200' => ! $event['is_interview'],
                                    ])
                                    style="top: {{ $top }}px; height: {{ $height }}px; left: calc({{ $event['lane'] * $width }}% + 2px); width: calc({{ $width }}% - 4px);"
                                    title="{{ $event['time_label'] }} · {{ $event['title'] }}">
                                    <p class="truncate font-semibold">{{ $event['title'] }}</p>
                                    @if ($height > 32)
                                        <p class="truncate text-[0.65rem] opacity-80">{{ $event['time_label'] }}</p>
                                    @endif
                                    @if ($event['subtitle'] && $height > 48)
                                        <p class="truncate text-[0.65rem] opacity-80">{{ $event['subtitle'] }}</p>
                                    @endif
                                    @if ($event['badge'] && $height > 64)
                                        <p class="truncate text-[0.65rem] opacity-70">{{ $event['badge'] }}</p>
                                    @endif
                                </{{ $event['url'] ? 'a' : 'div' }}>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
