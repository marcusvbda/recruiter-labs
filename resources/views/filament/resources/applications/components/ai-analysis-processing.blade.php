<div
    wire:poll.5s="$refresh"
    class="relative overflow-hidden rounded-xl border border-primary-200 bg-primary-50 px-6 py-10 dark:border-primary-800 dark:bg-primary-950/30"
    data-state="{{ $analysis['status'] }}"
>
    <div class="absolute -right-12 -top-12 size-40 rounded-full bg-primary-200/50 blur-3xl dark:bg-primary-700/20"></div>

    <div class="relative mx-auto flex max-w-2xl flex-col items-center gap-5 text-center">
        <div class="rl-analysis-active-indicator flex size-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-primary-200 dark:bg-gray-900 dark:ring-primary-700">
            <x-filament::loading-indicator class="size-7 text-primary-600 dark:text-primary-400" />
        </div>

        <div class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ $analysis['title'] }}
            </h2>

            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ $analysis['description'] }}
            </p>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-primary-700 shadow-sm ring-1 ring-primary-200 dark:bg-gray-900 dark:text-primary-300 dark:ring-primary-700">
            <span class="size-1.5 animate-pulse rounded-full bg-primary-500"></span>
            {{ $analysis['label'] }}
        </div>
    </div>
</div>
