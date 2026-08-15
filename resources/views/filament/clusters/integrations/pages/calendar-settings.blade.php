<x-filament-panels::page>
    @php
        $connection = $this->calendarConnection;
    @endphp

    <div class="grid gap-6" data-testid="calendar-settings">
        <section class="rl-settings-hero" aria-labelledby="calendar-settings-heading">
            <div class="absolute -top-20 -right-12 -z-10 size-64 rounded-full bg-cyan-300/20 blur-3xl"></div>
            <div class="absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-calendar-days" class="size-4" />
                    {{ __('calendar.eyebrow') }}
                </span>
                <h2 id="calendar-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ __('calendar.heading') }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                    {{ __('calendar.description') }}
                </p>
            </div>
        </section>

        <details class="group rl-settings-panel p-0" aria-labelledby="google-calendar-heading"
            data-testid="google-calendar-card">
            <summary class="flex cursor-pointer list-none items-center gap-4 [&::-webkit-details-marker]:hidden p-5">
                <span
                    class="flex size-12 shrink-0 p-2 shadown-md items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                    <img src="{{ $connection['plugin_icon'] }}" class="size-full object-contain" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span id="google-calendar-heading" class="font-semibold text-gray-950 dark:text-white">
                            {{ $connection['plugin_label'] }}
                        </span>
                        <x-filament::badge :color="$connection['is_connected']
                            ? 'success'
                            : ($connection['needs_reauthorization']
                                ? 'warning'
                                : 'gray')">
                            {{ $connection['status_label'] }}
                        </x-filament::badge>
                    </span>
                </span>
                <x-filament::icon icon="heroicon-o-chevron-down"
                    class="size-5 shrink-0 text-gray-400 transition-transform group-open:rotate-180 dark:text-gray-500" />
            </summary>

            <div class="mx-5">
                <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                    {{ $connection['plugin_category'] }}
                </p>

                <p class="mt-4 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $connection['needs_reauthorization']
                        ? __('calendar.google.reauthorization_description', ['plugin' => $connection['plugin_label']])
                        : $connection['plugin_description'] }}
                </p>
            </div>

            @if ($connection['is_connected'])
                <dl
                    class="mt-6 grid gap-4 border-t border-gray-100 p-5 sm:grid-cols-2 lg:grid-cols-3 dark:border-white/10">
                    @if ($connection['account_name'])
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                {{ __('calendar.details.account_name') }}
                            </dt>
                            <dd class="mt-1 truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $connection['account_name'] }}
                            </dd>
                        </div>
                    @endif
                    @if ($connection['account_email'])
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                {{ __('calendar.details.account_email') }}
                            </dt>
                            <dd class="mt-1 truncate text-sm font-medium text-gray-950 dark:text-white">
                                {{ $connection['account_email'] }}
                            </dd>
                        </div>
                    @endif
                    @if ($connection['connected_at'])
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                                {{ __('calendar.details.connected_at') }}
                            </dt>
                            <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                                {{ $connection['connected_at'] }}
                            </dd>
                        </div>
                    @endif
                </dl>
            @endif

            <div class="mt-3 flex shrink-0 flex-wrap items-center gap-2 p-5 pt-0">
                <x-filament::button tag="a" size="sm" class="self-center" :href="$connection['has_credentials'] ? $connection['reconnect_url'] : $connection['connect_url']"
                    :color="$connection['is_connected'] ? 'gray' : 'primary'" :outlined="$connection['is_connected']">
                    {{ $connection['has_credentials'] ? __('calendar.actions.reconnect') : __('calendar.actions.connect') }}
                </x-filament::button>
                @if ($connection['has_credentials'])
                    <x-filament::button color="danger" outlined wire:click="mountAction('disconnectGoogleCalendar')">
                        {{ __('calendar.actions.disconnect') }}
                    </x-filament::button>
                @endif
            </div>
        </details>
    </div>
</x-filament-panels::page>
