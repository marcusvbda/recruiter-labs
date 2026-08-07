<div class="rl-analysis-state" data-state="{{ $analysis['status'] }}">
    <div class="rounded-xl border border-danger-200 bg-danger-50 px-6 py-10 dark:border-danger-800 dark:bg-danger-950/30">
        <div class="mx-auto flex max-w-2xl flex-col items-center gap-4 text-center">
            <div class="flex size-14 items-center justify-center rounded-full bg-white shadow-sm ring-1 ring-danger-200 dark:bg-gray-900 dark:ring-danger-700">
                <x-filament::icon :icon="$analysis['icon']" class="size-7 text-danger-600 dark:text-danger-400" />
            </div>

            <div class="flex flex-col gap-2">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $analysis['title'] }}
                </h2>

                <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $analysis['description'] }}
                </p>
            </div>
        </div>
    </div>
</div>
