@if ($summary !== null)
    <div
        class="rl-company-summary"
        data-warning="{{ $summary['warning_state'] }}"
        data-testid="company-topbar-summary"
        aria-label="{{ __('settings.topbar.current_plan', ['plan' => $summary['plan_name']]) }}. {{ $summary['status_label'] }}"
    >
        <a
            href="{{ $summary['plan_url'] }}"
            class="rl-company-summary-link rl-company-summary-plan"
            aria-label="{{ __('settings.topbar.current_plan', ['plan' => $summary['plan_name']]) }}"
            x-tooltip="{
                content: @js($summary['plan_tooltip']),
                theme: $store.theme,
            }"
            data-testid="topbar-plan-link"
        >
            <x-filament::icon icon="heroicon-m-bolt" class="size-3.5 text-primary-500" />
            <span class="rl-company-summary-plan-label">{{ $summary['plan_name'] }}</span>
            <span class="sm:hidden">{{ $summary['plan_initial'] }}</span>
        </a>

        <span class="rl-company-summary-divider" aria-hidden="true"></span>

        <a
            href="{{ $summary['ai_url'] }}"
            class="rl-company-summary-link rl-company-summary-ai"
            aria-label="{{ __('settings.topbar.ai_analyses') }}: {{ $summary['used'] }} / {{ $summary['limit'] }}. {{ $summary['status_label'] }}"
            x-tooltip="{
                content: @js($summary['ai_tooltip']),
                theme: $store.theme,
            }"
            data-testid="topbar-ai-link"
        >
            <span class="flex items-center justify-between gap-2 leading-none">
                <span class="flex items-center gap-1">
                    @if ($summary['warning_state'] !== 'normal')
                        <x-filament::icon :icon="$summary['status_icon']" class="size-3.5" />
                    @endif
                    <span>{{ __('settings.topbar.ai_label') }}</span>
                </span>
                <span class="font-bold tabular-nums">
                    {{ $summary['used'] }} / {{ $summary['limit'] }}
                </span>
                <span class="rl-company-summary-percentage text-[0.65rem] text-gray-500 dark:text-gray-400">
                    {{ $summary['percentage_label'] }}
                </span>
            </span>
            <progress
                class="rl-progress-track h-1"
                max="100"
                value="{{ $summary['bar_percentage'] }}"
                aria-label="{{ $summary['percentage_label'] }}"
            ></progress>
            <span class="sr-only">{{ $summary['status_label'] }}</span>
        </a>
    </div>
@endif
