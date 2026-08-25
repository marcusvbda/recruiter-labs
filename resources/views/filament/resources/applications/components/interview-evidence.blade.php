{{--
    Interview evidence, grouped by criterion.

    Two labelled layers per criterion, deliberately never merged: what the
    submitted application supported before the interview, and what a human
    observed during it. There is no combined score, no human score and no
    "resolved N of M" — only a neutral count of the uncertainties the interviews
    left standing, because the hiring decision is not this section's to make.
--}}
<div class="space-y-4">
    @if ($evidence['criteria'] !== [])
        <div class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-gray-50/70 p-4 text-sm dark:border-white/10 dark:bg-white/5">
            <p class="text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-950 dark:text-white">{{ __('applications.admin.interviews.evidence.application_layer') }}:</span>
                {{ __('applications.admin.interviews.evidence.application_layer_hint') }}
            </p>
            <p class="text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-950 dark:text-white">{{ __('applications.admin.interviews.evidence.interview_layer') }}:</span>
                {{ __('applications.admin.interviews.evidence.interview_layer_hint') }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ trans_choice('applications.admin.interviews.evidence.unresolved_count', $evidence['unresolved_count'], ['count' => $evidence['unresolved_count']]) }}
            </p>
        </div>
    @endif

    @forelse ($evidence['criteria'] as $criterion)
        <article class="rounded-xl border border-gray-200 p-4 sm:p-5 dark:border-white/10">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $criterion['criterion'] }}</h3>
                    <div class="mt-1 flex flex-wrap gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                        <span>{{ __('applications.admin.ai.criteria.weight_label', ['weight' => $criterion['weight']]) }}</span>
                        <span>·</span>
                        <span>{{ __('applications.admin.ai.criteria.importance_label') }} {{ __('applications.admin.ai.criteria.importance.'.$criterion['importance']) }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- "Still unresolved" is a state, not a verdict: nobody has
                         been able to settle this criterion yet. --}}
                    @if ($criterion['is_unresolved'])
                        <x-filament::badge color="warning" icon="heroicon-o-question-mark-circle">
                            {{ __('applications.admin.interviews.evidence.unresolved_badge') }}
                        </x-filament::badge>
                    @endif
                    @if (! $criterion['is_current_criterion'])
                        <x-filament::badge color="gray" icon="heroicon-o-clock">
                            {{ __('applications.admin.interviews.evidence.removed_criterion') }}
                        </x-filament::badge>
                    @endif
                </div>
            </div>

            {{-- The pre-interview state, shown as it was recorded. Interview
                 feedback never rewrites it: a criterion the application could not
                 support stays unknown from the application, whatever the
                 interviewer later observed. --}}
            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50/70 p-3 dark:border-white/10 dark:bg-white/5">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    {{ __('applications.admin.interviews.evidence.application_layer') }}
                </p>
                @if ($criterion['application'])
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @if ($criterion['application']['is_assessed'])
                            <x-filament::badge color="gray">
                                {{ __('applications.admin.ai.criteria.fit_label', ['score' => $criterion['application']['score']]) }}
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="gray" icon="heroicon-o-question-mark-circle">
                                {{ __('applications.admin.ai.criteria.not_assessed') }}
                            </x-filament::badge>
                        @endif
                        <x-filament::badge :color="$criterion['application']['needs_validation'] ? 'warning' : 'success'" :icon="$criterion['application']['needs_validation'] ? 'heroicon-o-shield-exclamation' : 'heroicon-o-shield-check'">
                            {{ __('applications.admin.ai.confidence_label') }} {{ __('applications.admin.ai.confidence.'.$criterion['application']['confidence']) }}
                        </x-filament::badge>
                    </div>
                    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterion['application']['reason'] }}</p>
                @else
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __($evidence['shows_application_evidence']
                            ? 'applications.admin.interviews.evidence.application_absent'
                            : 'applications.admin.interviews.evidence.application_not_current') }}
                    </p>
                @endif
            </div>

            <div class="mt-4">
                <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                    {{ __('applications.admin.interviews.evidence.interview_layer') }}
                </p>
                <div class="mt-2 space-y-4">
                    @foreach ($criterion['entries'] as $entry)
                        @include('filament.resources.applications.components.interview-evidence-entry', ['entry' => $entry])
                    @endforeach
                </div>

                {{-- Feedback recorded before the job's criteria were edited stays
                     readable and stays here, in its own list: it answered an
                     earlier revision of this criterion, and folding it in with
                     today's would rewrite what the interviewer evaluated. --}}
                @if ($criterion['historical_entries'] !== [])
                    <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50/60 p-3 dark:border-warning-400/20 dark:bg-warning-500/10">
                        <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                            {{ __('applications.admin.interviews.evidence.historical_heading') }}
                        </p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            {{ __('applications.admin.interviews.evidence.historical_hint') }}
                        </p>
                        <div class="mt-3 space-y-4">
                            @foreach ($criterion['historical_entries'] as $entry)
                                @include('filament.resources.applications.components.interview-evidence-entry', ['entry' => $entry])
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </article>
    @empty
        <x-filament::empty-state
            :contained="false"
            :heading="__('applications.admin.interviews.evidence.empty')"
            :description="__('applications.admin.interviews.evidence.empty_description')"
            icon="heroicon-o-chat-bubble-left-right"
            icon-color="gray"
        />
    @endforelse
</div>
