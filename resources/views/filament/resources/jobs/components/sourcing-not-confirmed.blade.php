{{--
    Sourcing's blocked state: one quiet surface, no card-inside-card, matching
    the evaluation tab's own "awaiting criteria" presentation.
--}}
<div class="rl-analysis-state" data-state="blocked">
    <div class="rl-analysis-state__icon">
        <x-filament::icon icon="heroicon-o-clipboard-document-check" class="size-8" />
    </div>

    <div class="max-w-2xl text-center">
        <h2 class="mt-5 text-2xl font-bold text-gray-950 dark:text-white">{{ __('sourcing.not_confirmed.title') }}</h2>
        <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base dark:text-gray-300">
            {{ __('sourcing.not_confirmed.description') }}
        </p>
    </div>

    <div class="mt-7">
        <x-filament::button tag="a" :href="$confirmCriteriaUrl" icon="heroicon-m-arrow-top-right-on-square">
            {{ __('sourcing.not_confirmed.action') }}
        </x-filament::button>
    </div>
</div>
