<div
    wire:poll.5s="$refresh"
    class="relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 px-6 py-10 dark:border-gray-700 dark:bg-gray-800/40"
    data-state="{{ $analysis['status'] }}"
>
    <div class="relative mx-auto flex max-w-2xl flex-col items-center gap-5 text-center">
        <div class="flex size-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700">
            <x-filament::icon :icon="$analysis['icon']" class="size-7 text-gray-500 dark:text-gray-400" />
        </div>

        <div class="flex flex-col gap-2">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                {{ $analysis['title'] }}
            </h2>

            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ $analysis['description'] }}
            </p>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:text-gray-300 dark:ring-gray-700">
            <span class="size-1.5 rounded-full bg-gray-400"></span>
            {{ $analysis['label'] }}
        </div>
    </div>
</div>
