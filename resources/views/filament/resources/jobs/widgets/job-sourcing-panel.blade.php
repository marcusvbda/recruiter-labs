{{--
    The Sourcing panel: operational status, the "Find matches" control, the
    search summary, and the ranked list of current matches. Potential Match
    never appears without Evidence Coverage and Confidence beside it — that is
    a hard product rule, not a layout choice.
--}}
<x-filament-realtime-driver::listener
    :channel="'job_sourcing_'.$jobId"
    event="SourcingSearchUpdated"
    callback="$wire.$refresh()"
/>

<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
            <x-filament::badge :color="$operationalStatus['color']" :icon="$operationalStatus['icon']">
                <span class="sr-only">{{ __('sourcing.panel.status_label') }}</span>
                {{ $operationalStatus['label'] }}
            </x-filament::badge>

            @if ($operationalStatus['key'] === 'outdated')
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('sourcing.panel.outdated_hint') }}</span>
            @endif
        </div>

        @if ($eligibleCount > 0)
            <x-filament::button
                wire:click="findMatches"
                wire:loading.attr="disabled"
                wire:target="findMatches"
                :disabled="! $canFindMatches"
                icon="heroicon-m-magnifying-glass"
            >
                {{ __('sourcing.panel.find_matches') }}
            </x-filament::button>
        @endif
    </div>

    @if ($eligibleCount === 0 && $active === [] && $dismissed === [])
        <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
            {{ __('sourcing.panel.no_talent_pool') }}
        </p>
    @else
        @if ($summary !== null)
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('sourcing.panel.summary', [
                    'considered' => $summary['considered'],
                    'matched' => $summary['matched'],
                    'insufficient' => $summary['insufficient'],
                ]) }}
            </p>
        @elseif ($operationalStatus['key'] === 'not_started')
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('sourcing.panel.no_matches_yet') }}
            </p>
        @endif

        @if ($active !== [])
            <div class="space-y-4">
                @foreach ($active as $match)
                    @include('filament.resources.jobs.components.sourcing-match-card', ['match' => $match, 'dismissed' => false])
                @endforeach
            </div>
        @elseif ($summary !== null)
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                {{ __('sourcing.panel.no_current_matches') }}
            </p>
        @endif

        @if ($dismissed !== [])
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('sourcing.panel.dismissed_heading') }}
                </h3>
                <div class="mt-3 space-y-4">
                    @foreach ($dismissed as $match)
                        @include('filament.resources.jobs.components.sourcing-match-card', ['match' => $match, 'dismissed' => true])
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
