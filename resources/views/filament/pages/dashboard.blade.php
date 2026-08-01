<x-filament-panels::page>
    <div class="grid gap-6">
        <section class="relative isolate overflow-hidden rounded-3xl bg-linear-to-br from-primary-700 via-primary-600 to-cyan-500 px-6 py-8 text-white shadow-xl shadow-primary-900/15 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
            <div class="absolute -top-28 -right-20 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
            <div class="absolute -bottom-36 left-1/3 -z-10 size-96 rounded-full bg-cyan-300/20 blur-3xl"></div>

            <div class="grid items-center gap-8 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-12">
                <div class="max-w-3xl">
                    <div class="mb-6 flex flex-wrap items-center gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium backdrop-blur-sm">
                            <span class="size-2 rounded-full bg-emerald-300 shadow-sm shadow-emerald-200"></span>
                            {{ __('dashboard.welcome.workspace_ready') }}
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-medium backdrop-blur-sm">
                            <x-filament::icon icon="heroicon-o-building-office-2" class="size-4" />
                            {{ $companyName }}
                        </span>
                    </div>

                    <p class="text-sm font-semibold tracking-widest text-cyan-100 uppercase">
                        {{ __('dashboard.welcome.eyebrow') }}
                    </p>
                    <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                        <span
                            data-clock-greeting
                            data-good-morning="{{ __('dashboard.welcome.good_morning') }}"
                            data-good-afternoon="{{ __('dashboard.welcome.good_afternoon') }}"
                            data-good-evening="{{ __('dashboard.welcome.good_evening') }}"
                        >{{ $greeting }}</span>, {{ $userFirstName }}!
                    </h1>
                    <p class="mt-4 max-w-2xl text-base leading-7 text-blue-50 sm:text-lg">
                        {{ __('dashboard.welcome.introduction', ['company' => $companyName]) }}
                    </p>
                </div>

                <div class="relative mx-auto flex size-48 items-center justify-center sm:size-56 lg:size-64">
                    <div class="absolute inset-0 rounded-full border border-white/15 bg-white/5 backdrop-blur-sm"></div>
                    <div class="absolute inset-5 rounded-full border border-white/20 bg-white/10"></div>
                    <div class="relative flex size-32 items-center justify-center rounded-3xl border border-white/30 bg-white/15 shadow-2xl backdrop-blur-md sm:size-36">
                        <x-filament::icon icon="heroicon-o-briefcase" class="size-20 text-white sm:size-24" />
                        <span class="absolute -top-3 -right-3 flex size-12 items-center justify-center rounded-2xl bg-white text-primary-600 shadow-lg">
                            <x-filament::icon icon="heroicon-o-sparkles" class="size-7" />
                        </span>
                    </div>
                </div>
            </div>
        </section>

        <section aria-labelledby="workspace-context-heading">
            <div class="mb-4">
                <h2 id="workspace-context-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('dashboard.welcome.workspace_context') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('dashboard.welcome.workspace_context_description') }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                            <x-filament::icon icon="heroicon-o-building-office-2" class="size-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ __('dashboard.welcome.selected_company') }}
                            </p>
                            <p class="mt-1 truncate text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $companyName }}
                            </p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('dashboard.welcome.active_workspace') }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="flex items-start gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                            <span class="text-sm font-bold">{{ $userInitials }}</span>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ __('dashboard.welcome.signed_in_as') }}
                            </p>
                            <p class="mt-1 truncate text-lg font-semibold text-gray-950 dark:text-white">
                                {{ $userName }}
                            </p>
                            <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                                {{ $userEmail }}
                            </p>
                        </div>
                    </div>
                </article>

                <article class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm md:col-span-2 xl:col-span-1 dark:border-white/10 dark:bg-gray-900">
                    <welcome-clock class="block" data-locale="{{ str_replace('_', '-', app()->getLocale()) }}">
                        <div class="flex items-start gap-4">
                            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400">
                                <x-filament::icon icon="heroicon-o-clock" class="size-6" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                    {{ __('dashboard.welcome.local_time') }}
                                </p>
                                <time
                                    data-clock-time
                                    datetime="{{ now()->toIso8601String() }}"
                                    class="mt-1 block text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white"
                                >{{ now()->format('H:i:s') }}</time>
                                <p data-clock-date class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ now()->translatedFormat('l, d F Y') }}
                                </p>
                                <p data-clock-timezone class="mt-1 text-xs font-medium text-primary-600 dark:text-primary-400">
                                    {{ __('dashboard.welcome.browser_timezone') }}
                                </p>
                            </div>
                        </div>
                    </welcome-clock>
                </article>
            </div>
        </section>

        <section aria-labelledby="quick-access-heading">
            <div class="mb-4">
                <h2 id="quick-access-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('dashboard.welcome.quick_access') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('dashboard.welcome.quick_access_description') }}
                </p>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <a href="{{ $recruitmentUrl }}" class="group flex items-center gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/50">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-primary-50 text-primary-600 transition group-hover:bg-primary-600 group-hover:text-white dark:bg-primary-500/10 dark:text-primary-400">
                        <x-filament::icon icon="heroicon-o-briefcase" class="size-7" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-950 dark:text-white">
                            {{ __('dashboard.welcome.recruitment_title') }}
                        </span>
                        <span class="mt-1 block text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ __('dashboard.welcome.recruitment_description') }}
                        </span>
                    </span>
                    <x-filament::icon icon="heroicon-o-arrow-right" class="size-5 shrink-0 text-gray-400 transition group-hover:translate-x-1 group-hover:text-primary-600" />
                </a>

                <a href="{{ $automationUrl }}" class="group flex items-center gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-cyan-500/50">
                    <span class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 transition group-hover:bg-cyan-600 group-hover:text-white dark:bg-cyan-500/10 dark:text-cyan-400">
                        <x-filament::icon icon="heroicon-o-bolt" class="size-7" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-gray-950 dark:text-white">
                            {{ __('dashboard.welcome.automation_title') }}
                        </span>
                        <span class="mt-1 block text-sm leading-6 text-gray-500 dark:text-gray-400">
                            {{ __('dashboard.welcome.automation_description') }}
                        </span>
                    </span>
                    <x-filament::icon icon="heroicon-o-arrow-right" class="size-5 shrink-0 text-gray-400 transition group-hover:translate-x-1 group-hover:text-cyan-600" />
                </a>
            </div>
        </section>
    </div>

    @pushOnce('scripts')
        @vite('resources/js/filament/welcome-clock.ts')
    @endPushOnce
</x-filament-panels::page>
