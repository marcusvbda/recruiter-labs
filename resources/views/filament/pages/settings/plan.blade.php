@php
    $settings = $this->planSettings;
@endphp

<div class="grid gap-6" data-testid="plan-settings">
    <section class="rl-settings-hero" aria-labelledby="plan-settings-heading">
        <div class="absolute -top-24 -right-20 -z-10 size-72 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/3 -z-10 size-80 rounded-full bg-cyan-300/20 blur-3xl"></div>

        <div class="relative max-w-3xl">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-building-office-2" class="size-4" />
                    {{ __('settings.plan.eyebrow') }}
                </span>
                <span
                    class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-bold text-primary-700 shadow-sm">
                    {{ $settings['current_plan']['name'] }}
                </span>
            </div>
            <h2 id="plan-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                {{ __('settings.plan.heading') }}
            </h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                {{ __('settings.plan.description') }}
            </p>
        </div>
    </section>

    <div class="rl-plan-grid">
        @foreach ($settings['plans'] as $plan)
            <article class="rl-plan-card" data-current="{{ $plan['is_current'] ? 'true' : 'false' }}"
                data-testid="plan-card-{{ $plan['slug'] }}">
                @if ($plan['is_current'])
                    <div class="absolute inset-x-0 top-0 h-1 bg-linear-to-r from-primary-600 to-cyan-400"></div>
                @endif

                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                                {{ $plan['name'] }}
                            </h3>
                            @if ($plan['is_current'])
                                <x-filament::badge color="primary" size="sm">
                                    {{ __('settings.plan.current_badge') }}
                                </x-filament::badge>
                            @elseif ($plan['direction'] === 'upgrade')
                                <x-filament::badge color="success" size="sm">
                                    <x-filament::icon icon="heroicon-m-arrow-trending-up" class="mr-1 size-3.5" />
                                    {{ __('settings.plan.upgrade') }}
                                </x-filament::badge>
                            @else
                                <x-filament::badge color="gray" size="sm">
                                    <x-filament::icon icon="heroicon-m-arrow-trending-down" class="mr-1 size-3.5" />
                                    {{ __('settings.plan.downgrade') }}
                                </x-filament::badge>
                            @endif
                        </div>
                        <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ $plan['description'] }}
                        </p>
                    </div>
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon :icon="$plan['icon']" class="size-6" />
                    </span>
                </div>

                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-white/10">
                    <p class="text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {{ __('settings.plan.limits_heading') }}
                    </p>
                    <dl class="mt-3 grid gap-3">
                        @foreach ($plan['limits'] as $limit)
                            <div class="flex items-center justify-between gap-4 text-sm">
                                <dt class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <x-filament::icon :icon="$limit['icon']" class="size-4 text-gray-400" />
                                    {{ $limit['label'] }}
                                </dt>
                                <dd class="font-semibold tabular-nums text-gray-950 dark:text-white">
                                    {{ $limit['value'] }}
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </div>

                <div class="mt-5 grow border-t border-gray-100 pt-5 dark:border-white/10">
                    <p class="text-xs font-semibold tracking-wider text-gray-400 uppercase dark:text-gray-500">
                        {{ __('settings.plan.features_heading') }}
                    </p>
                    <ul class="mt-3 grid gap-2.5">
                        @forelse ($plan['features'] as $feature)
                            <li class="flex items-start gap-2.5 text-sm text-gray-600 dark:text-gray-300">
                                <span
                                    class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                                    <x-filament::icon icon="heroicon-m-check" class="size-3.5" />
                                </span>
                                {{ $feature }}
                            </li>
                        @empty
                            <li class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('settings.plan.no_extra_features') }}
                            </li>
                        @endforelse
                    </ul>
                </div>

                <div class="mt-6">
                    @if ($plan['is_current'])
                        <x-filament::button color="gray" class="w-full" disabled>
                            <x-filament::icon icon="heroicon-m-check-circle" class="mr-1.5 size-4" />
                            {{ __('settings.plan.current_plan') }}
                        </x-filament::button>
                    @else
                        <x-filament::button :color="$plan['direction'] === 'upgrade' ? 'primary' : 'gray'" class="w-full"
                            wire:click="mountAction('changePlan', { plan: {{ $plan['id'] }} })"
                            wire:loading.attr="disabled" wire:target="mountAction" :data-testid="'choose-plan-' . $plan['slug']">
                            {{ __('settings.plan.select', ['plan' => $plan['name']]) }}
                        </x-filament::button>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    <section class="rl-settings-panel" aria-labelledby="current-usage-heading" data-testid="current-usage">
        <div class="flex items-start gap-3">
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-chart-bar-square" class="size-5" />
            </span>
            <div>
                <h3 id="current-usage-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('settings.plan.current_usage') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('settings.plan.current_usage_description') }}
                </p>
            </div>
        </div>

        <div class="rl-usage-grid mt-5">
            @foreach ($settings['usage'] as $metric)
                <article class="rl-usage-card" data-warning="{{ $metric['warning_state'] }}"
                    data-testid="usage-{{ $metric['key'] }}">
                    <div class="flex items-start justify-between gap-3">
                        <span
                            class="flex size-9 items-center justify-center rounded-xl bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-300">
                            <x-filament::icon :icon="$metric['icon']" class="size-5" />
                        </span>
                        <x-filament::badge :color="$metric['badge_color']" size="sm">
                            {{ $metric['status_label'] }}
                        </x-filament::badge>
                    </div>
                    <p class="mt-4 text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $metric['label'] }}
                    </p>
                    <div class="mt-1 flex items-baseline gap-1.5">
                        <span class="text-2xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                            {{ $metric['used'] }}
                        </span>
                        <span class="text-sm font-medium text-gray-400 dark:text-gray-500">
                            / {{ $metric['limit'] }}
                        </span>
                    </div>
                    <progress class="rl-progress-track mt-4" max="100" value="{{ $metric['bar_percentage'] }}"
                        aria-label="{{ $metric['percentage_label'] }}"></progress>
                    <div class="mt-2 flex items-center justify-between gap-2 text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ $metric['percentage_label'] }}</span>
                        <span class="truncate">{{ $metric['cycle_label'] }}</span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div
        class="flex items-start gap-3 rounded-2xl border border-primary-200 bg-primary-50/70 p-4 text-sm text-primary-800 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-200">
        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-5 shrink-0" />
        <div>
            <p class="font-semibold">{{ __('settings.plan.manual_notice_title') }}</p>
            <p class="mt-1 leading-6">{{ __('settings.plan.manual_notice') }}</p>
        </div>
    </div>
</div>
