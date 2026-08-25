{{--
    One human observation about one criterion, attributed to the person who made
    it and to the interview it came from.

    The same row renders under the current criteria and under an earlier
    revision, so an observation cannot describe itself differently depending on
    which list it lands in.
--}}
<div class="border-s-2 border-gray-200 ps-3 dark:border-white/10">
    <div class="flex flex-wrap items-start justify-between gap-2">
        <x-filament::badge :color="$entry['result_color']" :icon="$entry['result_icon']">
            {{ $entry['result_label'] }}
        </x-filament::badge>
        <p class="text-xs text-gray-500 dark:text-gray-400">
            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $entry['author'] }}</span>
            · {{ __('applications.admin.interviews.evidence.submitted_at', ['date' => $entry['submitted_at']]) }}
            @if ($entry['interview_at'])
                · {{ __('applications.admin.interviews.evidence.interview_label', ['date' => $entry['interview_at']]) }}
            @endif
        </p>
    </div>

    {{-- Feedback can only be recorded on an interview that has taken place, but
         the interview can be cancelled or moved afterwards. The observation was
         really made, so it stays — said plainly, so it is not read as evidence
         from a commitment that never happened. --}}
    @if ($entry['interview_state'] !== 'held')
        <p class="mt-2 text-xs text-warning-700 dark:text-warning-400">
            {{ __('applications.admin.interviews.evidence.interview_states.'.$entry['interview_state']) }}
        </p>
    @endif

    @if ($entry['recorded_as'])
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {{ __('applications.admin.interviews.evidence.recorded_as', ['criterion' => $entry['recorded_as']]) }}
        </p>
    @endif

    @if (filled($entry['note']))
        <p class="mt-2 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
            {{ __($entry['is_assertion']
                ? 'applications.admin.interviews.evidence.human_evidence_label'
                : 'applications.admin.interviews.evidence.not_assessed_note_label') }}
        </p>
        <p class="mt-1 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $entry['note'] }}</p>
    @elseif ($entry['is_assertion'])
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            {{ __('applications.admin.interviews.evidence.no_note') }}
        </p>
    @endif
</div>
