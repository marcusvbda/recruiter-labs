{{--
    A finished evaluation that measured criteria this job has since changed. The
    stored result is not deleted — it is simply not presented as the current
    assessment, because it answers a question the job no longer asks.
--}}
<div class="rl-analysis-state" data-state="outdated">
    <div class="rl-analysis-state__icon">
        <x-filament::icon :icon="$analysis['icon']" class="size-8" />
    </div>

    <div class="max-w-2xl text-center">
        <x-filament::badge color="warning" icon="heroicon-m-arrow-path">
            {{ $analysis['label'] }}
        </x-filament::badge>
        <h2 class="mt-5 text-2xl font-bold text-gray-950 dark:text-white">{{ $analysis['title'] }}</h2>
        <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base dark:text-gray-300">
            {{ $analysis['description'] }}
        </p>
    </div>

    <div class="mt-7 flex items-center gap-2 rounded-xl bg-white/70 px-4 py-2.5 text-xs font-medium text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        <x-filament::icon icon="heroicon-m-inbox-arrow-down" class="size-4" />
        {{ __('applications.admin.ai.received_at', ['date' => $analysis['received_at']]) }}
    </div>
</div>
