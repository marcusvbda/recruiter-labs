<x-filament-panels::page>
    @php
        $providers = $this->emailProviderSettings;
        $gmail = $this->gmailConnection;
    @endphp

    <div class="grid gap-6" data-testid="email-provider-settings">
        <section class="rl-settings-hero" aria-labelledby="email-provider-settings-heading">
            <div class="absolute -top-20 -right-12 -z-10 size-64 rounded-full bg-cyan-300/20 blur-3xl"></div>
            <div class="absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-envelope" class="size-4" />
                    {{ __('email_provider.eyebrow') }}
                </span>
                <h2 id="email-provider-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ __('email_provider.heading') }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                    {{ __('email_provider.description') }}
                </p>
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="email-provider-cards">
            @foreach ($providers as $provider)
                <div class="rl-settings-panel" data-testid="email-provider-card-{{ $provider['provider'] }}">
                    <div class="flex items-center gap-3">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-black p-2">
                            <img src="{{ $provider['icon'] }}" alt="{{ $provider['provider_label'] }}"
                                class="size-full object-contain" />
                        </span>
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-gray-950 dark:text-white">
                                {{ $provider['provider_label'] }}
                            </h3>
                            @if ($provider['is_configured'])
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span
                                        class="size-2 rounded-full {{ $provider['is_default'] ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $provider['is_default'] ? __('email_provider.default_badge') : $provider['credential_status_label'] }}
                                    </span>
                                </div>
                            @elseif ($provider['has_key'])
                                <span class="text-xs font-medium text-amber-600 dark:text-amber-400">
                                    Setup incomplete
                                </span>
                            @else
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ __('email_provider.empty.heading') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($provider['has_key'])
                        <div
                            class="mt-4 flex flex-wrap items-center gap-2 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-white/5">
                            <code
                                class="text-sm font-semibold text-gray-700 dark:text-gray-200">{{ $provider['masked_key'] }}</code>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $provider['credential_status_label'] }} · {{ $provider['last_validated_label'] }}
                            </span>
                            @if ($provider['has_sender_address'])
                                <span class="basis-full truncate text-xs text-gray-500 dark:text-gray-400">
                                    From {{ $provider['from_address'] }}
                                </span>
                            @else
                                <span class="basis-full text-xs font-medium text-amber-600 dark:text-amber-400">
                                    Add a sender email address to enable recruitment emails.
                                </span>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <x-filament::button size="sm" color="gray" outlined
                                wire:click="mountAction('configureProvider', { provider: '{{ $provider['provider'] }}' })">
                                {{ __('email_provider.actions.replace') }}
                            </x-filament::button>
                            @if ($provider['is_configured'])
                                <x-filament::button size="sm" color="gray" outlined
                                    wire:click="mountAction('testProvider', { provider: '{{ $provider['provider'] }}' })">
                                    {{ __('email_provider.actions.test') }}
                                </x-filament::button>
                                @unless ($provider['is_default'])
                                    <x-filament::button size="sm" color="primary" outlined
                                        wire:click="mountAction('setDefaultProvider', { provider: '{{ $provider['provider'] }}' })">
                                        {{ __('email_provider.actions.set_default') }}
                                    </x-filament::button>
                                @endunless
                            @endif
                            <x-filament::button size="sm" color="danger" outlined
                                wire:click="mountAction('removeProvider', { provider: '{{ $provider['provider'] }}' })">
                                {{ __('email_provider.actions.remove') }}
                            </x-filament::button>
                        </div>
                    @else
                        <div class="mt-4">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('email_provider.empty.description') }}
                            </p>
                            <x-filament::button size="sm" color="primary" class="mt-3"
                                wire:click="mountAction('configureProvider', { provider: '{{ $provider['provider'] }}' })">
                                {{ __('email_provider.actions.configure') }}
                            </x-filament::button>
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="rl-settings-panel" data-testid="email-provider-card-gmail">
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300">
                        <x-filament::icon :icon="$gmail['plugin_icon']" class="size-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-gray-500">
                            {{ $gmail['plugin_category'] }}
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="truncate font-semibold text-gray-950 dark:text-white">
                                {{ $gmail['plugin_label'] }}
                            </h3>
                            <x-filament::badge :color="$gmail['needs_reauthorization'] ? 'warning' : ($gmail['is_connected'] ? 'success' : 'gray')">
                                {{ $gmail['status_label'] }}
                            </x-filament::badge>
                        </div>
                    </div>
                </div>

                <p class="mt-4 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ $gmail['needs_reauthorization']
                        ? __('email_provider.gmail.reauthorization_description', ['plugin' => $gmail['plugin_label']])
                        : $gmail['plugin_description'] }}
                </p>

                @if ($gmail['default_uses_another_connection'])
                    <div
                        class="mt-4 flex items-start gap-2 rounded-xl bg-blue-50 px-3 py-2.5 text-sm text-blue-700 dark:bg-blue-500/10 dark:text-blue-200">
                        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 size-4 shrink-0" />
                        <span>{{ __('email_provider.gmail.default_uses_another_connection', ['plugin' => $gmail['plugin_label']]) }}</span>
                    </div>
                @endif

                @if ($gmail['is_connected'])
                    <dl class="mt-4 grid gap-3 rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/5">
                        @if ($gmail['account_name'])
                            <div>
                                <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                    {{ __('email_provider.gmail.details.account_name') }}
                                </dt>
                                <dd class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ $gmail['account_name'] }}
                                </dd>
                            </div>
                        @endif
                        @if ($gmail['account_email'])
                            <div>
                                <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                    {{ __('email_provider.gmail.details.account_email') }}
                                </dt>
                                <dd class="mt-0.5 truncate text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ $gmail['account_email'] }}
                                </dd>
                            </div>
                        @endif
                        @if ($gmail['connected_at'])
                            <div>
                                <dt class="text-xs font-medium text-gray-400 dark:text-gray-500">
                                    {{ __('email_provider.gmail.details.connected_at') }}
                                </dt>
                                <dd class="mt-0.5 text-sm font-medium text-gray-800 dark:text-gray-100">
                                    {{ $gmail['connected_at'] }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button tag="a" size="sm"
                        :href="$gmail['has_credentials'] ? $gmail['reconnect_url'] : $gmail['connect_url']"
                        :color="$gmail['is_connected'] ? 'gray' : 'primary'" :outlined="$gmail['is_connected']">
                        {{ $gmail['has_credentials']
                            ? __('email_provider.gmail.actions.reconnect')
                            : __('email_provider.gmail.actions.connect', ['plugin' => $gmail['plugin_label']]) }}
                    </x-filament::button>
                    @if ($gmail['can_set_default'])
                        <x-filament::button size="sm" color="primary" outlined
                            wire:click="mountAction('setDefaultProvider', { provider: 'gmail' })">
                            {{ __('email_provider.actions.set_default') }}
                        </x-filament::button>
                    @endif
                    @if ($gmail['has_credentials'])
                        <x-filament::button size="sm" color="danger" outlined
                            wire:click="mountAction('disconnectGmail')">
                            {{ __('email_provider.gmail.actions.disconnect') }}
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
