<div class="flex flex-col gap-6">
    @if ($this->batchErrors() !== [])
        <x-filament::section :heading="__('candidate_imports.review.file_errors_heading')">
            <ul class="list-disc pl-5 text-sm text-danger-600 dark:text-danger-400">
                @foreach ($this->batchErrors() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament::section :heading="__('candidate_imports.review.rows_heading')">
        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __('candidate_imports.review.filter_label') }}">
            @php
                $filters = ['all', 'needs_decision', 'blocked', 'importable'];
                $counts = $this->filterCounts();
            @endphp

            @foreach ($filters as $filter)
                <x-filament::button
                    size="sm"
                    :color="$statusFilter === $filter ? 'primary' : 'gray'"
                    :outlined="$statusFilter !== $filter"
                    wire:click="setStatusFilter('{{ $filter }}')"
                    :aria-pressed="$statusFilter === $filter ? 'true' : 'false'"
                >
                    {{ __('candidate_imports.review.filters.'.$filter) }} ({{ $counts[$filter] }})
                </x-filament::button>
            @endforeach
        </div>

        <div class="flex flex-col gap-3">
            @forelse ($this->filteredRows() as $row)
                <div
                    wire:key="import-row-{{ $row->recordNumber }}"
                    class="rounded-lg border p-4 {{ $row->duplicateGroup() !== null ? 'border-warning-400 dark:border-warning-500' : 'border-gray-200 dark:border-white/10' }}"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900 dark:text-white">
                                #{{ $row->recordNumber }} — {{ $row->field('name') ?? __('candidate_imports.review.unnamed') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $row->field('email') ?? '—' }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge color="gray">{{ $this->identityLabel($row) }}</x-filament::badge>

                            @if ($row->isExcluded())
                                <x-filament::badge color="danger">{{ __('candidate_imports.review.statuses.excluded') }}</x-filament::badge>
                            @elseif ($row->isBlocked())
                                <x-filament::badge color="danger">{{ __('candidate_imports.review.statuses.blocked') }}</x-filament::badge>
                            @elseif ($row->needsDecision())
                                <x-filament::badge color="warning">{{ __('candidate_imports.review.statuses.needs_decision') }}</x-filament::badge>
                            @elseif ($row->willImport())
                                <x-filament::badge color="success">{{ __('candidate_imports.review.statuses.importable') }}</x-filament::badge>
                            @endif

                            @if ($row->duplicateGroup() !== null)
                                <x-filament::badge color="warning">{{ __('candidate_imports.review.duplicate_group') }}</x-filament::badge>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-1 gap-2 text-sm sm:grid-cols-3">
                        <div>
                            <dt class="font-medium text-gray-700 dark:text-gray-200">{{ __('candidate_imports.review.fields.cv_filename') }}</dt>
                            <dd class="text-gray-500 dark:text-gray-400">{{ $row->cvFilename() ?? __('candidate_imports.review.none') }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-700 dark:text-gray-200">{{ __('candidate_imports.review.fields.received_on') }}</dt>
                            <dd class="text-gray-500 dark:text-gray-400">{{ $row->receivedOn() ?? __('candidate_imports.review.none') }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-700 dark:text-gray-200">{{ __('candidate_imports.review.fields.source_label') }}</dt>
                            <dd class="text-gray-500 dark:text-gray-400">{{ $row->field('source_label') ?? __('candidate_imports.review.none') }}</dd>
                        </div>
                    </dl>

                    @if ($row->issuesToArray() !== [])
                        <ul class="mt-3 list-disc pl-5 text-xs text-gray-600 dark:text-gray-300">
                            @foreach ($row->issuesToArray() as $issue)
                                <li>{{ $this->issueLabel($issue) }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($this->canConfirmReuse($row))
                        @php $existing = $this->existingNameContext($row); @endphp
                        @if ($existing)
                            <p class="mt-3 text-xs text-gray-600 dark:text-gray-300">
                                {{ __('candidate_imports.review.existing_name_prompt', ['existing' => $existing['existing'], 'supplied' => $existing['supplied']]) }}
                            </p>
                        @endif
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($this->canInclude($row))
                            <x-filament::button size="sm" color="success" outlined wire:click="mountAction('includeRow', { record: {{ $row->recordNumber }} })">
                                {{ __('candidate_imports.review.actions.include') }}
                            </x-filament::button>
                        @endif

                        @if ($this->canExclude($row))
                            <x-filament::button size="sm" color="danger" outlined wire:click="mountAction('excludeRow', { record: {{ $row->recordNumber }} })">
                                {{ __('candidate_imports.review.actions.exclude') }}
                            </x-filament::button>
                        @endif

                        @if ($this->canConfirmReuse($row))
                            <x-filament::button
                                size="sm"
                                color="primary"
                                outlined
                                wire:click="mountAction('confirmReuseRow', { record: {{ $row->recordNumber }}, existing_candidate_id: {{ $row->existingCandidateId() }} })"
                            >
                                {{ __('candidate_imports.review.actions.confirm_reuse') }}
                            </x-filament::button>
                        @endif

                        @if ($this->canImportContactOnly($row))
                            <x-filament::button size="sm" color="gray" outlined wire:click="mountAction('contactOnlyRow', { record: {{ $row->recordNumber }} })">
                                {{ __('candidate_imports.review.actions.contact_only') }}
                            </x-filament::button>
                        @endif

                        @if ($row->duplicateGroup() !== null && ! $row->isExcluded())
                            <x-filament::button size="sm" color="warning" outlined wire:click="mountAction('chooseDuplicateRow', { record: {{ $row->recordNumber }} })">
                                {{ __('candidate_imports.review.actions.choose_duplicate') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('candidate_imports.review.no_rows') }}</p>
            @endforelse
        </div>
    </x-filament::section>

    <x-filament::section :heading="__('candidate_imports.review.summary_heading')">
        <p class="text-sm font-medium text-gray-900 dark:text-white">
            {{ $this->summary()->statement() }}
        </p>

        <div class="mt-4">
            {{ $this->confirmImportAction }}
        </div>
    </x-filament::section>
</div>
