@php
    $boardColumns = $this->getBoardColumns();
    $transitions = $this->getTransitionMapForView();
    $columnLabels = $this->getColumnLabelsForView();
    $totalCount = $this->getTotalCount();
    $visibleCount = collect($boardColumns)->sum('shown');
    $recordsPerColumn = $this->getRecordsPerColumnLimit();
@endphp

<x-filament-widgets::widget>
    @filamentStyles(['filament-model-states'])

    <x-filament::section :heading="__('applications.pipeline.kanban.heading')">
        <div
            x-data="kanbanBoard({
                transitions: @js($transitions),
                labels: @js($columnLabels),
                confirmBeforeMove: @js($this->shouldConfirmBeforeMove()),
            })"
            x-on:kanban-move-accepted.window="handleMoveAccepted($event.detail)"
            x-on:kanban-move-rejected.window="handleMoveRejected()"
        >
            <div class="fi-model-states-kanban__toolbar">
                <div class="fi-model-states-kanban__search">
                    <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                        <x-filament::input
                            wire:model.live.debounce.500ms="search"
                            :placeholder="__('applications.pipeline.kanban.search_placeholder')"
                        />
                    </x-filament::input.wrapper>
                </div>

                <div class="fi-model-states-kanban__filter">
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="columnFilter">
                            <option value="">{{ __('applications.pipeline.kanban.all_statuses') }}</option>
                            @foreach ($this->getColumns() as $columnKey)
                                <option value="{{ $columnKey }}">{{ $this->getColumnLabel($columnKey) }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <p class="fi-model-states-kanban__stats">
                    {{ __('applications.pipeline.kanban.showing') }} <strong>{{ number_format($visibleCount) }}</strong>
                    {{ __('applications.pipeline.kanban.of') }} <strong>{{ number_format($totalCount) }}</strong>
                    {{ trans_choice('applications.pipeline.kanban.applications', $totalCount) }}
                    @if ($recordsPerColumn < $totalCount)
                        <span class="fi-model-states-kanban__stats-note">
                            {{ __('applications.pipeline.kanban.up_to_per_column', ['count' => number_format($recordsPerColumn)]) }}
                        </span>
                    @endif
                </p>
            </div>

            <div
                class="fi-model-states-kanban"
                style="grid-template-columns: repeat({{ count($boardColumns) }}, minmax(15rem, 1fr));"
            >
                @foreach ($boardColumns as $column)
                    @php
                        $columnKey = $column['key'];
                        $records = $column['records'];
                        $color = $this->getColumnColor($columnKey);
                        $outgoing = $this->getOutgoingTransitions($columnKey);
                    @endphp

                    <div class="fi-model-states-kanban__column" data-column="{{ $columnKey }}">
                        <div class="fi-model-states-kanban__column-header">
                            <span class="fi-model-states-kanban__column-title">
                                {{ $this->getColumnLabel($columnKey) }}
                            </span>
                            <x-filament::badge :color="$color">
                                <span data-count-column="{{ $columnKey }}">
                                    {{ $column['shown'] }}@if ($column['has_more']) / {{ $column['total'] }}@endif
                                </span>
                            </x-filament::badge>
                        </div>

                        @if (count($outgoing))
                            <div
                                class="fi-model-states-kanban__transitions"
                                title="{{ __('applications.pipeline.kanban.allowed_transitions_from', ['status' => $this->getColumnLabel($columnKey)]) }}"
                            >
                                <span class="fi-model-states-kanban__transitions-label">
                                    {{ __('applications.pipeline.kanban.can_move_to') }}
                                </span>
                                @foreach ($outgoing as $target)
                                    <span class="fi-model-states-kanban__transition-chip">
                                        {{ $this->getColumnLabel($target) }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <p class="fi-model-states-kanban__terminal-note">
                                {{ __('applications.pipeline.kanban.terminal_status') }}
                            </p>
                        @endif

                        <div
                            class="fi-model-states-kanban__column-body fi-model-states-column"
                            data-column="{{ $columnKey }}"
                        >
                            @forelse ($records as $record)
                                <div
                                    data-record-id="{{ $record->getKey() }}"
                                    class="fi-model-states-kanban__card fi-model-states-card"
                                >
                                    <div class="flex items-start justify-between gap-2">
                                        <a
                                            href="{{ $this->getApplicationUrl($record) }}"
                                            wire:navigate
                                            x-on:pointerdown.stop
                                            x-on:click.stop
                                            class="fi-model-states-kanban__card-title hover:text-primary-600 focus-visible:ring-primary-500 rounded-sm outline-none focus-visible:ring-2"
                                            title="{{ __('applications.admin.actions.view_application') }}"
                                        >
                                            {{ $this->getCardTitle($record) }}
                                        </a>
                                        <x-filament::badge
                                            :color="$this->getAnalysisColor($record)"
                                            icon="heroicon-m-sparkles"
                                        >
                                            {{ $this->getAnalysisLabel($record) }}
                                        </x-filament::badge>
                                    </div>
                                    @if ($subtitle = $this->getCardSubtitle($record))
                                        <p class="fi-model-states-kanban__card-subtitle">
                                            {{ $subtitle }}
                                        </p>
                                    @endif
                                </div>
                            @empty
                                <p class="fi-model-states-kanban__empty-column">
                                    {{ __('applications.pipeline.kanban.no_matching_applications') }}
                                </p>
                            @endforelse
                        </div>

                        @if ($column['has_more'])
                            <p class="fi-model-states-kanban__more-note">
                                {{ trans_choice(
                                    'applications.pipeline.kanban.more_applications',
                                    $column['total'] - $column['shown'],
                                    ['count' => number_format($column['total'] - $column['shown'])],
                                ) }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>

            <template x-teleport="body">
                <div
                    x-show="showConfirm"
                    x-cloak
                    x-transition.opacity
                    class="fi-model-states-kanban__confirm-overlay"
                    role="dialog"
                    aria-modal="true"
                    x-on:keydown.escape.window="cancelMove()"
                >
                    <div
                        class="fi-model-states-kanban__confirm-dialog"
                        x-on:click.outside="cancelMove()"
                    >
                        <h3 class="fi-model-states-kanban__confirm-title">
                            {{ __('applications.pipeline.kanban.confirm_title') }}
                        </h3>
                        <p class="fi-model-states-kanban__confirm-body" x-show="pendingMove">
                            {{ __('applications.pipeline.kanban.move') }}
                            <strong x-text="pendingMove ? pendingMove.cardTitle : ''"></strong>
                            {{ __('applications.pipeline.kanban.from') }}
                            <strong x-text="pendingMove ? pendingMove.sourceLabel : ''"></strong>
                            {{ __('applications.pipeline.kanban.to') }}
                            <strong x-text="pendingMove ? pendingMove.targetLabel : ''"></strong>?
                        </p>
                        <div class="fi-model-states-kanban__confirm-actions">
                            <x-filament::button color="gray" tag="button" type="button" x-on:click="cancelMove()">
                                {{ __('applications.pipeline.kanban.cancel') }}
                            </x-filament::button>
                            <x-filament::button tag="button" type="button" x-on:click="confirmMove()">
                                {{ __('applications.pipeline.kanban.confirm_move') }}
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>

    @filamentScripts(['filament-model-states'])
</x-filament-widgets::widget>
