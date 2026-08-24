{{--
    A finished extraction is a draft. This banner exists so nobody can mistake
    it for a decision: the criteria below govern candidate evaluation only after
    a recruiter confirms them.
--}}
<div class="rounded-xl border border-gray-200 bg-gray-50 px-5 py-5 dark:border-white/10 dark:bg-white/5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <div class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-white text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
                <x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-5" />
            </div>

            <div class="max-w-2xl">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $wasConfirmedBefore
                        ? __('jobs.criteria.awaiting_review.changed_title')
                        : __('jobs.criteria.awaiting_review.title') }}
                </p>
                <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ $wasConfirmedBefore
                        ? __('jobs.criteria.awaiting_review.changed_description')
                        : __('jobs.criteria.awaiting_review.description') }}
                </p>

                @if ($waitingApplications > 0)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        {{ trans_choice('jobs.criteria.awaiting_review.waiting_applications', $waitingApplications, ['count' => $waitingApplications]) }}
                    </p>
                @endif
            </div>
        </div>

        <x-filament::badge color="warning" icon="heroicon-m-sparkles">
            {{ __('jobs.criteria.awaiting_review.badge') }}
        </x-filament::badge>
    </div>
</div>
