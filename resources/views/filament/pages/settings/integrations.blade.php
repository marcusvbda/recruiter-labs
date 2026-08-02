<section class="rl-settings-panel" aria-labelledby="integrations-settings-heading">
    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
            <x-filament::icon icon="heroicon-o-squares-plus" class="size-5" />
        </span>
        <div>
            <h2 id="integrations-settings-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ __('settings.integrations.heading') }}
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ __('settings.integrations.description') }}
            </p>
        </div>
    </div>

    <div class="mt-6 flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-6 py-12 text-center dark:border-white/15 dark:bg-white/5">
        <span class="flex size-14 items-center justify-center rounded-2xl bg-white text-primary-600 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-primary-400 dark:ring-white/10">
            <x-filament::icon icon="heroicon-o-puzzle-piece" class="size-7" />
        </span>
        <h3 class="mt-4 font-semibold text-gray-950 dark:text-white">{{ __('settings.integrations.placeholder_title') }}</h3>
        <p class="mt-1 max-w-lg text-sm leading-6 text-gray-500 dark:text-gray-400">
            {{ __('settings.integrations.placeholder') }}
        </p>
    </div>
</section>
