<div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-950">
    <div class="flex flex-col gap-1 border-b border-gray-200 px-5 py-4 sm:px-6 dark:border-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ __('jobs.edit_tabs.preview_title') }}
        </h3>

        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('jobs.edit_tabs.preview_description') }}
        </p>
    </div>

    <iframe
        src="{{ $previewUrl }}"
        title="{{ __('jobs.edit_tabs.preview_title') }}"
        class="h-[72vh] min-h-[44rem] w-full bg-slate-50"
    ></iframe>
</div>
