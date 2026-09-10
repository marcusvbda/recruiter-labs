<x-filament-panels::page>
    <x-filament::section :heading="__('candidate_imports.upload.template_heading')">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('candidate_imports.upload.template_description') }}
        </p>

        <dl class="mt-4 grid grid-cols-1 gap-2 text-sm sm:grid-cols-2">
            @foreach (\App\Services\CandidateImportCsvReader::HEADERS as $header)
                <div>
                    <dt class="font-medium text-gray-700 dark:text-gray-200">{{ $header }}</dt>
                    <dd class="text-gray-500 dark:text-gray-400">{{ __('candidate_imports.field_guide.'.$header) }}</dd>
                </div>
            @endforeach
        </dl>

        <x-filament::button tag="a" href="{{ route('candidate-import-batches.template') }}" color="gray" class="mt-4">
            {{ __('candidate_imports.upload.actions.download_template') }}
        </x-filament::button>
    </x-filament::section>

    @if (! $batch)
        <x-filament::section :heading="__('candidate_imports.upload.start_heading')">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('candidate_imports.upload.start_description') }}
            </p>

            {{ $this->startImportAction }}
        </x-filament::section>
    @else
        <div wire:key="candidate-import-batch-{{ $batch->getKey() }}" class="flex flex-col gap-6">
            <x-filament::section :heading="__('candidate_imports.upload.settings_heading')">
                <dl class="rl-application-detail-grid">
                    <div>
                        <dt>{{ __('candidate_imports.fields.source_label') }}</dt>
                        <dd>{{ $batch->source_label }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('candidate_imports.fields.separator') }}</dt>
                        <dd>{{ $batch->separator === ';' ? __('candidate_imports.separators.semicolon') : __('candidate_imports.separators.comma') }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('candidate_imports.fields.correction_mode') }}</dt>
                        <dd>{{ $batch->correction_mode ? __('candidate_imports.yes') : __('candidate_imports.no') }}</dd>
                    </div>
                </dl>

                <div class="mt-4 flex gap-2">
                    {{ $this->editSettingsAction }}
                    {{ $this->discardAction }}
                </div>
            </x-filament::section>

            <x-filament::section :heading="__('candidate_imports.upload.limits_heading')">
                <ul class="list-disc pl-5 text-sm text-gray-500 dark:text-gray-400">
                    <li>{{ __('candidate_imports.upload.limits.rows', ['count' => $this->limitsData()['rows']]) }}</li>
                    <li>{{ __('candidate_imports.upload.limits.csv_size', ['size' => $this->limitsData()['csv_size']]) }}</li>
                    <li>{{ __('candidate_imports.upload.limits.cv_size', ['size' => $this->limitsData()['cv_size']]) }}</li>
                    <li>{{ __('candidate_imports.upload.limits.batch_files', ['count' => $this->limitsData()['batch_files']]) }}</li>
                    <li>{{ __('candidate_imports.upload.limits.batch_cv_size', ['size' => $this->limitsData()['batch_cv_size']]) }}</li>
                </ul>
            </x-filament::section>

            <x-filament::section :heading="__('candidate_imports.upload.csv_heading')">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('candidate_imports.upload.csv_description', ['headers' => implode(', ', \App\Services\CandidateImportCsvReader::HEADERS)]) }}
                </p>

                <div class="mt-4">
                    {{ $this->uploadCsvAction }}
                </div>

                @if ($batch->csv_original_name)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        {{ __('candidate_imports.upload.csv_on_file', ['name' => $batch->csv_original_name]) }}
                    </p>
                @endif

                @php $csvErrors = $this->csvErrors(); @endphp
                @if (count($csvErrors))
                    <div role="alert" class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                        <ul class="list-disc pl-5">
                            @foreach ($csvErrors as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            <x-filament::section :heading="__('candidate_imports.upload.cvs_heading')">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('candidate_imports.upload.cvs_running_total', ['files' => $held['files'], 'size' => \Illuminate\Support\Number::fileSize($held['bytes'])]) }}
                </p>

                <div class="mt-4">
                    {{ $this->uploadCvFilesAction }}
                </div>

                @php $fileIssues = $this->fileIssues(); @endphp
                @if (count($fileIssues))
                    <div role="alert" class="mt-4 rounded-lg bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400">
                        <ul class="list-disc pl-5">
                            @foreach ($fileIssues as $file)
                                <li>
                                    <strong>{{ $file['name'] }}</strong>:
                                    {{ implode(', ', $file['issues']) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </x-filament::section>

            @if ($this->isReady())
                <x-filament::section :heading="__('candidate_imports.upload.ready_heading')">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('candidate_imports.upload.ready_description') }}
                    </p>

                    @if ($this->reviewUrl())
                        <x-filament::button tag="a" href="{{ $this->reviewUrl() }}" class="mt-4">
                            {{ __('candidate_imports.upload.actions.continue_to_review') }}
                        </x-filament::button>
                    @else
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('candidate_imports.upload.review_not_available') }}
                        </p>
                    @endif
                </x-filament::section>
            @else
                <x-filament::section :heading="__('candidate_imports.upload.status_heading')">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('candidate_imports.statuses.'.$batch->status->value) }}
                    </p>
                </x-filament::section>
            @endif
        </div>
    @endif
</x-filament-panels::page>
