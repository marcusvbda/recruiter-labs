{{--
    One sourcing suggestion. Potential Match, Evidence Coverage and Confidence
    always render together — never Potential Match alone — and previous
    recruitment history stays visually separate from the current assessment,
    labelled as historical context rather than proof of fit.
--}}
<div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($match['candidate_url'])
                    <a href="{{ $match['candidate_url'] }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        {{ $match['candidate_name'] }}
                    </a>
                @else
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $match['candidate_name'] }}</span>
                @endif

                @if ($match['state'] === 'saved')
                    <x-filament::badge color="success">{{ __('sourcing.panel.state_saved') }}</x-filament::badge>
                @endif

                @if ($match['already_in_job'])
                    <x-filament::badge color="gray" icon="heroicon-m-check-circle">
                        {{ __('sourcing.panel.already_in_job') }}
                    </x-filament::badge>
                @endif

                @if ($match['is_outdated'])
                    <x-filament::badge color="warning" icon="heroicon-m-arrow-path">
                        {{ __('sourcing.status.outdated') }}
                    </x-filament::badge>
                @endif
            </div>

            @if ($match['sufficient_information'])
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <x-filament::badge color="gray">
                        {{ __('sourcing.match.potential_match_label') }}: {{ $match['potential_match'] !== null ? "{$match['potential_match']}%" : __('sourcing.match.not_assessed') }}
                    </x-filament::badge>
                    <x-filament::badge color="gray">
                        {{ __('sourcing.match.evidence_coverage_label') }}: {{ $match['evidence_coverage'] !== null ? "{$match['evidence_coverage']}%" : __('sourcing.match.not_assessed') }}
                    </x-filament::badge>
                    @if ($match['confidence'])
                        <x-filament::badge color="gray">
                            {{ __('sourcing.match.confidence_label') }}: {{ __("sourcing.match.confidence.{$match['confidence']}") }}
                        </x-filament::badge>
                    @endif
                </div>
            @else
                <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                    {{ __('sourcing.panel.insufficient_information') }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($dismissed)
                <x-filament::button wire:click="restore({{ $match['id'] }})" color="gray" icon="heroicon-m-arrow-uturn-left" size="sm">
                    {{ __('sourcing.panel.restore_action') }}
                </x-filament::button>
            @else
                @if ($match['state'] !== 'saved' && ! $match['already_in_job'])
                    <x-filament::button wire:click="save({{ $match['id'] }})" color="gray" icon="heroicon-m-bookmark" size="sm">
                        {{ __('sourcing.panel.save_action') }}
                    </x-filament::button>
                @endif

                <x-filament::button wire:click="dismiss({{ $match['id'] }})" color="gray" icon="heroicon-m-x-mark" size="sm">
                    {{ __('sourcing.panel.dismiss_action') }}
                </x-filament::button>

                @if ($match['candidate_url'])
                    <x-filament::button tag="a" :href="$match['candidate_url']" color="gray" icon="heroicon-m-user" size="sm">
                        {{ __('sourcing.panel.open_candidate_action') }}
                    </x-filament::button>
                @endif

                @if ($match['communication_url'])
                    <x-filament::button tag="a" :href="$match['communication_url']" color="gray" icon="heroicon-m-envelope" size="sm">
                        {{ __('communications.actions.contact_candidate') }}
                    </x-filament::button>
                @endif

                @if (! $match['already_in_job'])
                    <x-filament::button wire:click="addToJob({{ $match['id'] }})" icon="heroicon-m-user-plus" size="sm">
                        {{ __('sourcing.panel.add_to_job_action') }}
                    </x-filament::button>
                @endif
            @endif
        </div>
    </div>

    @if ($match['sufficient_information'] && ($match['criteria']['strong_support'] !== [] || $match['criteria']['needs_validation'] !== [] || $match['criteria']['insufficient'] !== []))
        <div class="mt-4 space-y-3">
            @foreach ([
                'strong_support' => __('sourcing.criteria.strong_support_heading'),
                'needs_validation' => __('sourcing.criteria.needs_validation_heading'),
                'insufficient' => __('sourcing.criteria.insufficient_heading'),
            ] as $group => $heading)
                @if ($match['criteria'][$group] !== [])
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $heading }}</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($match['criteria'][$group] as $criterionScore)
                                <li class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $criterionScore['criterion'] }}</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">({{ __('sourcing.criteria.weight_label', ['weight' => $criterionScore['weight']]) }})</span>
                                    — {{ $criterionScore['reason'] }}
                                    @if ($criterionScore['evidence'] !== [])
                                        <ul class="mt-1 ms-4 list-disc space-y-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            @foreach ($criterionScore['evidence'] as $evidence)
                                                <li>
                                                    {{ $evidence['source'] }}: {{ $evidence['detail'] }}
                                                    @if ($evidence['submitted_at'])
                                                        — {{ __('sourcing.criteria.evidence_submitted_on', ['date' => $evidence['submitted_at']]) }}
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if ($match['history'] !== [])
        <div class="mt-4 border-t border-gray-100 pt-3 dark:border-white/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('sourcing.history.heading') }}
            </p>
            <ul class="mt-1 space-y-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
                @foreach ($match['history'] as $entry)
                    <li>
                        {{ $entry['job_name'] }} — {{ __('sourcing.history.applied_on', ['date' => $entry['applied_at']]) }}, {{ $entry['status_name'] }}
                        @if ($entry['was_hired'])
                            · {{ __('sourcing.history.was_hired') }}
                        @elseif ($entry['is_closed'])
                            · {{ __('sourcing.history.closed') }}
                        @elseif ($entry['reached_final_stage'])
                            · {{ __('sourcing.history.reached_final_stage') }}
                        @endif
                    </li>
                @endforeach
                @if ($match['history_hidden_count'] > 0)
                    <li class="text-xs text-gray-400 dark:text-gray-500">{{ __('sourcing.history.more', ['count' => $match['history_hidden_count']]) }}</li>
                @endif
            </ul>
            <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ __('sourcing.history.not_proof_of_fit') }}</p>
        </div>
    @endif
</div>
