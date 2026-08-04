@php
    $settings = $this->aiSettings;
@endphp

<div class="grid gap-6" data-testid="ai-settings">
    <section class="rl-settings-hero" aria-labelledby="ai-settings-heading">
        <div class="absolute -top-20 -right-12 -z-10 size-64 rounded-full bg-violet-300/25 blur-3xl"></div>
        <div class="absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-cyan-300/20 blur-3xl"></div>
        <div class="relative grid items-center gap-6 lg:grid-cols-[minmax(0,1fr)_auto]">
            <div class="max-w-3xl">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-sparkles" class="size-4" />
                    {{ __('settings.ai.eyebrow') }}
                </span>
                <h2 id="ai-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ __('settings.ai.heading') }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                    {{ __('settings.ai.description') }}
                </p>
            </div>
            <div class="flex items-center gap-3 rounded-2xl border border-white/20 bg-white/10 p-4 backdrop-blur-sm">
                <span class="flex size-11 items-center justify-center rounded-xl bg-white text-primary-700 shadow-sm">
                    <x-filament::icon icon="heroicon-o-bolt" class="size-6" />
                </span>
                <div>
                    <p class="text-xs font-medium text-blue-100">{{ __('settings.ai.current_provider') }}</p>
                    <p class="mt-0.5 font-semibold text-white">{{ $settings['provider_label'] }}</p>
                    {{-- <p class="text-xs text-blue-100">{{ $settings['model'] }}</p> --}}
                </div>
            </div>
        </div>
    </section>

    <section class="rl-settings-panel" aria-labelledby="ai-provider-heading">
        <div>
            <h3 id="ai-provider-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ __('settings.ai.provider_heading') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('settings.ai.provider_description') }}
            </p>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <article @class([
                'relative rounded-2xl border p-5 transition',
                'border-primary-500 bg-primary-50/50 ring-2 ring-primary-500/10 dark:border-primary-400 dark:bg-primary-500/5' =>
                    $settings['provider'] === 'platform',
                'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-950/30' =>
                    $settings['provider'] !== 'platform',
            ])>
                <div class="flex items-start justify-between gap-4">
                    <span
                        class="flex size-11 items-center justify-center rounded-2xl bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-sparkles" class="size-6" />
                    </span>
                    @if ($settings['provider'] === 'platform')
                        <x-filament::badge color="primary" size="sm">
                            {{ __('settings.ai.status.active') }}
                        </x-filament::badge>
                    @endif
                </div>
                <h4 class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('settings.ai.platform.name') }}</h4>
                <p class="mt-1 min-h-10 text-sm leading-5 text-gray-500 dark:text-gray-400">
                    {{ __('settings.ai.platform.description') }}
                </p>
                <div class="mt-5">
                    <x-filament::button color="gray" wire:click="mountAction('usePlatformAi')"
                        wire:loading.attr="disabled" :disabled="$settings['configured_provider'] === 'platform'">
                        {{ $settings['configured_provider'] === 'platform' ? __('settings.ai.status.active') : __('settings.ai.platform.action') }}
                    </x-filament::button>
                </div>
            </article>

            <article @class([
                'relative rounded-2xl border p-5 transition',
                'border-primary-500 bg-primary-50/50 ring-2 ring-primary-500/10 dark:border-primary-400 dark:bg-primary-500/5' =>
                    $settings['provider'] === 'own',
                'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-950/30' =>
                    $settings['provider'] !== 'own',
                'opacity-75' => !$settings['own_key_allowed'],
            ])>
                <div class="flex items-start justify-between gap-4">
                    <span
                        class="flex size-11 items-center justify-center rounded-2xl bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                        <x-filament::icon icon="heroicon-o-key" class="size-6" />
                    </span>
                    @if ($settings['provider'] === 'own')
                        <x-filament::badge color="success"
                            size="sm">{{ __('settings.ai.status.active') }}</x-filament::badge>
                    @elseif (!$settings['own_key_allowed'])
                        <x-filament::badge color="warning" size="sm">
                            <span class="flex items-center gap-1">
                                <x-filament::icon icon="heroicon-m-lock-closed" class="size-3.5" />
                                {{ __('settings.ai.own_key.required_plan', ['plan' => $settings['own_key_required_plan']]) }}
                            </span>
                        </x-filament::badge>
                    @endif
                </div>
                <h4 class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('settings.ai.own_key.name') }}</h4>
                <p class="mt-1 text-sm leading-5 text-gray-500 dark:text-gray-400">
                    {{ __('settings.ai.own_key.description') }}
                </p>

                @if ($settings['has_own_key'])
                    <div
                        class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-white/5">
                        <code
                            class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $settings['masked_key'] }}</code>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $settings['credential_status_label'] }} · {{ $settings['last_validated_label'] }}
                        </span>
                    </div>
                @elseif (!$settings['own_key_allowed'])
                    <p class="mt-4 flex items-start gap-2 text-sm text-amber-700 dark:text-amber-300">
                        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-4 shrink-0" />
                        {{ __('settings.ai.own_key.locked') }}
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap gap-2">
                    @if ($settings['own_key_allowed'])
                        <x-filament::button color="primary" wire:click="mountAction('configureOwnAi')"
                            wire:loading.attr="disabled">
                            {{ $settings['has_own_key'] ? __('settings.ai.own_key.replace') : __('settings.ai.own_key.action') }}
                        </x-filament::button>
                    @else
                        <x-filament::button tag="a" :href="$settings['plan_url']" color="warning" outlined>
                            {{ __('settings.plan.upgrade') }}
                        </x-filament::button>
                    @endif

                    @if ($settings['has_own_key'])
                        @if ($settings['own_key_allowed'])
                            <x-filament::button color="gray" wire:click="mountAction('testOwnAi')" outlined>
                                {{ __('settings.ai.own_key.test') }}
                            </x-filament::button>
                        @endif
                        <x-filament::button color="danger" wire:click="mountAction('removeOwnAi')" outlined>
                            {{ __('settings.ai.own_key.remove') }}
                        </x-filament::button>
                    @endif
                </div>
            </article>
        </div>
    </section>

    <section class="rl-settings-panel" aria-labelledby="ai-usage-heading" data-testid="ai-usage">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 id="ai-usage-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('settings.ai.usage.heading') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('settings.ai.usage.description') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::badge color="gray">{{ __('settings.ai.usage.platform') }}:
                    {{ $settings['usage']['platform_used'] }}</x-filament::badge>
                <x-filament::badge color="gray">{{ __('settings.ai.usage.own') }}:
                    {{ $settings['usage']['own_used'] }}</x-filament::badge>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
            <div>
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-bold tracking-tight text-gray-950 tabular-nums dark:text-white">
                            {{ $settings['usage']['used'] }}
                        </span>
                        <span class="text-sm font-medium text-gray-400">/ {{ $settings['usage']['limit'] }}</span>
                    </div>
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                        {{ $settings['usage']['percentage_label'] }}
                    </span>
                </div>
                <progress class="rl-progress-track mt-3 h-3" max="100"
                    value="{{ $settings['usage']['bar_percentage'] }}"
                    aria-label="{{ $settings['usage']['percentage_label'] }}"></progress>
                <div
                    class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <span>{{ $settings['usage']['remaining_label'] }}</span>
                    <span>{{ $settings['usage']['cycle_label'] }}</span>
                </div>
            </div>
            <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                    {{ __('settings.ai.current_provider') }}</p>
                <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $settings['provider_label'] }}</p>
            </div>
        </div>
    </section>

    <section class="rl-settings-panel" aria-labelledby="ai-history-heading" data-testid="ai-usage-history">
        <div>
            <h3 id="ai-history-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ __('settings.ai.history.heading') }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('settings.ai.history.description') }}
            </p>
        </div>

        @if ($settings['history'] === [])
            <div
                class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-10 text-center dark:border-white/15 dark:bg-white/5">
                <span
                    class="flex size-14 items-center justify-center rounded-2xl bg-white text-primary-600 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-primary-400 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-o-cpu-chip" class="size-7" />
                </span>
                <h4 class="mt-4 font-semibold text-gray-950 dark:text-white">
                    {{ __('settings.ai.history.empty_heading') }}</h4>
                <p class="mt-1 max-w-lg text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ __('settings.ai.history.empty_description') }}
                </p>
            </div>
        @else
            <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                <table class="w-full text-left text-sm">
                    <thead
                        class="bg-gray-50 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            @foreach (['date', 'operation', 'model', 'tokens', 'cost', 'provider', 'status'] as $column)
                                <th class="px-4 py-3">{{ __('settings.ai.history.' . $column) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($settings['history'] as $record)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="whitespace-nowrap px-4 py-3">{{ $record['date'] }}</td>
                                <td class="px-4 py-3 font-medium">{{ $record['operation'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $record['model'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ $record['tokens'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums">{{ $record['cost'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3">{{ $record['provider'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <x-filament::badge :color="$record['status_color']"
                                        size="sm">{{ $record['status'] }}</x-filament::badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
