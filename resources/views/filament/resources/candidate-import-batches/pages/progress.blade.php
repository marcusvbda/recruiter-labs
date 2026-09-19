<x-filament-panels::page>
    <x-filament-realtime-driver::listener
        :channel="'candidate_import_batch_'.$batch->id"
        event="CandidateImportBatchUpdated"
        callback="$wire.$refresh()"
    />
    <x-filament::section :heading="__('candidate_imports.progress.status_heading')">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-200">
            {{ __('candidate_imports.statuses.'.$batch->status->value) }}
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $this->stateMessage() }}
        </p>

        @if ($this->failureMessage())
            <div role="alert" class="mt-4 rounded-lg bg-warning-50 p-4 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                {{ $this->failureMessage() }}
            </div>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            {{ $this->resumeAction() }}
            {{ $this->retryAction() }}
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('candidate_imports.progress.counters_heading')">
        @php $progress = $this->progress(); @endphp
        <dl class="rl-application-detail-grid">
            <div>
                <dt>{{ __('candidate_imports.progress.counters.selected') }}</dt>
                <dd>{{ $progress->selected }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.processed') }}</dt>
                <dd>{{ $progress->processed }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.remaining') }}</dt>
                <dd>{{ $progress->remaining() }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.created') }}</dt>
                <dd>{{ $progress->created }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.reused') }}</dt>
                <dd>{{ $progress->reused }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.unchanged') }}</dt>
                <dd>{{ $progress->unchanged }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.cvs_retained') }}</dt>
                <dd>{{ $progress->cvsRetained }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.cvs_duplicate') }}</dt>
                <dd>{{ $progress->cvsDuplicate }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.needs_review') }}</dt>
                <dd>{{ $progress->needsReview }}</dd>
            </div>
            <div>
                <dt>{{ __('candidate_imports.progress.counters.failed') }}</dt>
                <dd>{{ $progress->failed }}</dd>
            </div>
        </dl>
    </x-filament::section>

    <x-filament::section :heading="__('candidate_imports.progress.downloads_heading')">
        <div class="flex flex-col gap-4">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('candidate_imports.progress.downloads.report_description') }}
                </p>
                <x-filament::button tag="a" href="{{ $this->reportUrl() }}" color="gray" class="mt-2">
                    {{ __('candidate_imports.progress.downloads.report_link') }}
                </x-filament::button>
            </div>

            <div>
                @if ($this->correctionUrl())
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('candidate_imports.progress.downloads.correction_description') }}
                    </p>
                    <x-filament::button tag="a" href="{{ $this->correctionUrl() }}" color="gray" class="mt-2">
                        {{ __('candidate_imports.progress.downloads.correction_link') }}
                    </x-filament::button>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('candidate_imports.progress.downloads.correction_unavailable') }}
                    </p>
                @endif
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
