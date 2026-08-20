{{--
    Rendered only when the workspace's AI allowance is close to — or already at —
    the point where candidate evaluations stop running. The normal state renders
    nothing here: plan and AI usage details live in Settings.
--}}
@if ($summary !== null)
    <div
        class="rl-company-summary"
        data-warning="{{ $summary['warning_state'] }}"
        data-testid="company-topbar-summary"
        aria-label="{{ __('settings.topbar.ai_analyses') }}. {{ $summary['status_label'] }}"
    >
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
                    <x-filament::icon :icon="$summary['status_icon']" class="size-3.5" />
                    <span>{{ $summary['status_label'] }}</span>
                </span>
                <span class="font-bold tabular-nums">
                    {{ $summary['used'] }} / {{ $summary['limit'] }}
                </span>
            </span>
            <progress
                class="rl-progress-track h-1"
                max="100"
                value="{{ $summary['bar_percentage'] }}"
                aria-label="{{ $summary['percentage_label'] }}"
            ></progress>
        </a>
    </div>
@endif
