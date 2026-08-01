<x-filament-panels::page>
    <div class="fi-tabs flex gap-x-2">
        <button
            type="button"
            wire:click="$set('activeView', 'kanban')"
            @class([
                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                'bg-primary-600 text-white' => $activeView === 'kanban',
                'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $activeView !== 'kanban',
            ])
        >
            {{ __('applications.pipeline.view_kanban') }}
        </button>

        <button
            type="button"
            wire:click="$set('activeView', 'list')"
            @class([
                'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
                'bg-primary-600 text-white' => $activeView === 'list',
                'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' => $activeView !== 'list',
            ])
        >
            {{ __('applications.pipeline.view_list') }}
        </button>
    </div>

    @if ($activeView === 'kanban')
        <div class="flex gap-4 overflow-x-auto pb-4">
            @foreach ($statuses as $status)
                <div class="flex w-72 shrink-0 flex-col rounded-xl bg-gray-50 p-3 dark:bg-white/5">
                    <div class="mb-3 flex items-center gap-x-2 px-1">
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            style="background-color: {{ $status->color }}"
                        ></span>

                        <span class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $status->name }}
                        </span>

                        <span class="ms-auto text-xs text-gray-500 dark:text-gray-400">
                            {{ $applicationsByStatus->get($status->id, collect())->count() }}
                        </span>
                    </div>

                    <div
                        x-sortable
                        x-sortable-group="pipeline-{{ $record->id }}"
                        data-status-id="{{ $status->id }}"
                        x-on:end.stop="$wire.moveApplication($event.item.getAttribute('x-sortable-item'), $event.to.dataset.statusId)"
                        class="flex min-h-16 flex-1 flex-col gap-2"
                    >
                        @foreach ($applicationsByStatus->get($status->id, collect()) as $application)
                            <div
                                wire:key="pipeline-application-{{ $application->id }}"
                                x-sortable-item="{{ $application->id }}"
                                x-sortable-handle
                                class="cursor-grab rounded-lg border border-gray-200 bg-white p-3 shadow-sm active:cursor-grabbing dark:border-white/10 dark:bg-gray-900"
                            >
                                <p class="text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $application->candidate->name }}
                                </p>

                                @if ($application->candidate->email)
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $application->candidate->email }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <table class="fi-ta-table w-full text-start">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        {{ __('applications.fields.candidate') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        {{ __('applications.fields.status') }}
                    </th>
                    <th class="px-3 py-2 text-start text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                        {{ __('applications.fields.created_at') }}
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse ($applicationsByStatus->flatten() as $application)
                    <tr class="border-b border-gray-100 dark:border-white/5">
                        <td class="px-3 py-2 text-sm text-gray-950 dark:text-white">
                            {{ $application->candidate->name }}
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-700 dark:text-gray-300">
                            <span class="inline-flex items-center gap-x-1.5">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    style="background-color: {{ $application->status->color }}"
                                ></span>
                                {{ $application->status->name }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ $application->created_at->format('Y-m-d') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                            {{ __('applications.pipeline.no_candidates') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endif
</x-filament-panels::page>
