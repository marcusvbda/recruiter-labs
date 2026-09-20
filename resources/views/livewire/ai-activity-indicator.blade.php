{{--
    Persistent AI indicator + the AI Activity slide-over behind it.

    Everything rendered here comes from real persisted operation state. There
    is no progress bar, no percentage and no animated "thinking" state that
    isn't backed by a row: "Up to date" is a valid and useful answer.
--}}
<div class="flex items-center">
    {{-- Realtime refresh instead of wire:poll: any write that moves an AI
         operation status broadcasts on this workspace's channel. --}}
    <x-filament-realtime-driver::listener
        :channel="$channel"
        :event="$event"
        callback="$wire.$refresh()"
    />

    <x-filament::modal
        id="ai-activity-panel"
        slide-over
        width="lg"
        :heading="__('ai_activity.panel.heading')"
        :description="__('ai_activity.panel.description')"
    >
        <x-slot name="trigger">
            <button
                type="button"
                class="flex items-center gap-1.5 rounded-lg px-2 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"
                :title="__('ai_activity.indicator.open')"
            >
                <span class="sr-only">{{ __('ai_activity.indicator.label') }}</span>

                <x-filament::badge :color="$activity['state_color']" :icon="$activity['state_icon']">
                    {{ $activity['state_label'] }}@if ($activity['indicator_count'] !== null)
                        <span class="tabular-nums"> · {{ $activity['indicator_count'] }}</span>
                    @endif
                </x-filament::badge>
            </button>
        </x-slot>

        @php
            $sections = [
                ['key' => 'blocked', 'heading' => __('ai_activity.panel.blocked_heading'), 'color' => 'danger'],
                ['key' => 'working', 'heading' => __('ai_activity.panel.working_heading'), 'color' => 'info'],
                ['key' => 'waiting', 'heading' => __('ai_activity.panel.waiting_heading'), 'color' => 'warning'],
            ];
        @endphp

        <div class="flex flex-col gap-6">
            @if ($activity['working'] === [] && $activity['waiting'] === [] && $activity['blocked'] === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai_activity.panel.empty') }}</p>
            @endif

            @foreach ($sections as $section)
                @if ($activity[$section['key']] !== [])
                    <div class="flex flex-col gap-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $section['heading'] }}
                        </h3>

                        @foreach ($activity[$section['key']] as $item)
                            <x-ai-activity-item :item="$item" :color="$section['color']" />
                        @endforeach
                    </div>
                @endif
            @endforeach

            <div class="flex flex-col gap-2 border-t border-gray-200 pt-4 dark:border-white/10">
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('ai_activity.panel.recent_heading') }}
                </h3>

                @forelse ($activity['recent'] as $item)
                    <x-ai-activity-item :item="$item" color="gray" />
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('ai_activity.panel.recent_empty') }}</p>
                @endforelse
            </div>
        </div>
    </x-filament::modal>
</div>
