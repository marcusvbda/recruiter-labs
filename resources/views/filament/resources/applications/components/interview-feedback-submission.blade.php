{{--
    One interviewer's submission, on the card of the interview it came from.

    It stays a block of its own: several submissions on one interview are
    several attributed blocks, never a merged summary. Two interviewers who
    observed different things are both reporting what they saw.
--}}
<article class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $submission['author'] }}</p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                {{ __('applications.admin.interviews.evidence.human_authored') }}
                · {{ __('applications.admin.interviews.evidence.submitted_at', ['date' => $submission['submitted_at']]) }}
            </p>
        </div>
        @if ($submission['is_historical'])
            <x-filament::badge color="warning" icon="heroicon-o-clock">
                {{ __('applications.admin.interviews.evidence.historical_badge') }}
            </x-filament::badge>
        @endif
    </div>

    {{-- Feedback can only be recorded on an interview that has taken place, but
         the interview can be cancelled or moved afterwards. The observation was
         really made, so it stays — said plainly, so it is not read as evidence
         from a commitment that never happened. --}}
    @if ($submission['interview_state'] !== 'held')
        <p class="mt-2 text-xs text-warning-700 dark:text-warning-400">
            {{ __('applications.admin.interviews.evidence.interview_states.'.$submission['interview_state']) }}
        </p>
    @endif

    @if (filled($submission['general_note']))
        <div class="mt-3">
            <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                {{ __('applications.admin.interviews.feedback.general_note') }}
            </p>
            <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $submission['general_note'] }}</p>
        </div>
    @endif

    <ul class="mt-3 space-y-3">
        @foreach ($submission['criteria'] as $criterionResult)
            <li class="border-t border-gray-200 pt-3 dark:border-white/10">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $criterionResult['criterion'] }}</p>
                    <x-filament::badge :color="$criterionResult['result_color']" :icon="$criterionResult['result_icon']">
                        {{ $criterionResult['result_label'] }}
                    </x-filament::badge>
                </div>

                {{-- A note on "Not assessed" is the interviewer explaining why the
                     criterion was not covered, so it never renders under a heading
                     that reads as evidence about the candidate. --}}
                @if (filled($criterionResult['note']))
                    <p class="mt-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {{ __($criterionResult['is_assertion']
                            ? 'applications.admin.interviews.evidence.human_evidence_label'
                            : 'applications.admin.interviews.evidence.not_assessed_note_label') }}
                    </p>
                    <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterionResult['note'] }}</p>
                @endif
            </li>
        @endforeach
    </ul>
</article>
