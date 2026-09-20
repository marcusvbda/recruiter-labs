<x-filament::section :heading="__('candidates.materials.heading')" :description="__('candidates.materials.description')" icon="heroicon-o-document-text">
    @if ($importOrigin)
        <div
            class="mb-3 flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-gray-100 pb-3 text-sm text-gray-600 dark:border-white/5 dark:text-gray-400">
            <span
                class="font-medium text-gray-700 dark:text-gray-300">{{ __('candidates.materials.import_origin') }}:</span>
            <span>
                {{ __('candidates.materials.import_origin_summary', [
                    'source' => $importOrigin['source_label'] ?? __('candidates.materials.unknown_source'),
                    'date' => $importOrigin['added_at'],
                ]) }}
            </span>
            @if ($importOrigin['added_by'])
                <span>· {{ __('candidates.materials.added_by', ['name' => $importOrigin['added_by']]) }}</span>
            @endif
            @if ($importOrigin['has_batch'])
                <span>· {{ __('candidates.materials.from_batch') }}</span>
            @endif
        </div>
    @endif

    @forelse ($available as $material)
        <div class="border-b border-gray-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="truncate font-medium text-gray-950 dark:text-white">
                        {{ $material['original_name'] ?? __('candidates.materials.unnamed_file') }}
                        <span
                            class="font-normal text-gray-500 dark:text-gray-400">({{ strtoupper($material['extension']) }}
                            · {{ $material['size'] }})</span>
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('candidates.materials.added_on', ['date' => $material['added_at']]) }}
                        @if ($material['added_by'])
                            · {{ __('candidates.materials.added_by', ['name' => $material['added_by']]) }}
                        @endif
                        @if ($material['source_label'])
                            · {{ $material['source_label'] }}
                        @endif
                    </p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        @if ($material['received_on'])
                            {{ __('candidates.materials.received_on', ['date' => $material['received_on']]) }}
                        @else
                            {{ __('candidates.materials.received_on_unknown') }}
                        @endif
                        @if ($material['has_batch'])
                            · {{ __('candidates.materials.from_batch') }}
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $material['status_label'] }}
                    </span>

                    @if ($material['text_is_partial'])
                        <x-filament::badge color="warning" size="sm">
                            {{ __('candidates.materials.partial_text') }}
                        </x-filament::badge>
                    @endif

                    @if ($material['view_url'])
                        <x-filament::link :href="$material['view_url']" icon="heroicon-m-eye" icon-position="after" size="sm"
                            target="_blank">
                            {{ __('candidates.materials.view') }}
                        </x-filament::link>
                    @endif

                    @if ($material['download_url'])
                        <x-filament::link :href="$material['download_url']" icon="heroicon-m-arrow-down-tray" icon-position="after"
                            size="sm">
                            {{ __('candidates.materials.download') }}
                        </x-filament::link>
                    @endif

                    <x-filament::button size="sm" color="gray" outlined
                        wire:click="mountAction('correctMaterialMetadata', { material: {{ $material['id'] }} })">
                        {{ __('candidates.materials.actions.correct_metadata') }}
                    </x-filament::button>

                    @if ($material['can_retry'])
                        <x-filament::button size="sm" color="gray" outlined
                            wire:click="mountAction('retryMaterialPreparation', { material: {{ $material['id'] }} })">
                            {{ __('candidates.materials.actions.retry') }}
                        </x-filament::button>
                    @endif

                    <x-filament::button size="sm" color="gray" outlined
                        wire:click="mountAction('archiveMaterial', { material: {{ $material['id'] }} })">
                        {{ __('candidates.materials.actions.archive') }}
                    </x-filament::button>

                    <x-filament::button size="sm" color="danger" outlined
                        wire:click="mountAction('deleteMaterial', { material: {{ $material['id'] }} })">
                        {{ __('candidates.materials.actions.delete') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('candidates.materials.no_materials') }}</p>
    @endforelse

    @if ($archivedCount > 0)
        <div x-data="{ showArchived: false }" class="mt-3 border-t border-gray-100 pt-3 dark:border-white/5">
            <button type="button" x-on:click="showArchived = ! showArchived"
                class="text-sm font-medium text-gray-700 hover:text-primary-600 dark:text-gray-300"
                :aria-expanded="showArchived">
                <span
                    x-show="! showArchived">{{ __('candidates.materials.show_archived', ['count' => $archivedCount]) }}</span>
                <span x-show="showArchived" style="display: none">{{ __('candidates.materials.hide_archived') }}</span>
            </button>

            <div x-show="showArchived" style="display: none" class="mt-3">
                @foreach ($archived as $material)
                    <div class="border-b border-gray-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-950 dark:text-white">
                                    {{ $material['original_name'] ?? __('candidates.materials.unnamed_file') }}
                                    <span
                                        class="font-normal text-gray-500 dark:text-gray-400">({{ strtoupper($material['extension']) }}
                                        · {{ $material['size'] }})</span>
                                </p>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('candidates.materials.archived') }}
                                    · {{ __('candidates.materials.added_on', ['date' => $material['added_at']]) }}
                                    @if ($material['added_by'])
                                        · {{ __('candidates.materials.added_by', ['name' => $material['added_by']]) }}
                                    @endif
                                    @if ($material['source_label'])
                                        · {{ $material['source_label'] }}
                                    @endif
                                </p>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    @if ($material['received_on'])
                                        {{ __('candidates.materials.received_on', ['date' => $material['received_on']]) }}
                                    @else
                                        {{ __('candidates.materials.received_on_unknown') }}
                                    @endif
                                    @if ($material['has_batch'])
                                        · {{ __('candidates.materials.from_batch') }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">
                                    {{ $material['status_label'] }}
                                </span>

                                @if ($material['text_is_partial'])
                                    <x-filament::badge color="warning" size="sm">
                                        {{ __('candidates.materials.partial_text') }}
                                    </x-filament::badge>
                                @endif

                                @if ($material['view_url'])
                                    <x-filament::link :href="$material['view_url']" icon="heroicon-m-eye" icon-position="after"
                                        size="sm" target="_blank">
                                        {{ __('candidates.materials.view') }}
                                    </x-filament::link>
                                @endif

                                @if ($material['download_url'])
                                    <x-filament::link :href="$material['download_url']" icon="heroicon-m-arrow-down-tray"
                                        icon-position="after" size="sm">
                                        {{ __('candidates.materials.download') }}
                                    </x-filament::link>
                                @endif

                                <x-filament::button size="sm" color="gray" outlined
                                    wire:click="mountAction('correctMaterialMetadata', { material: {{ $material['id'] }} })">
                                    {{ __('candidates.materials.actions.correct_metadata') }}
                                </x-filament::button>

                                <x-filament::button size="sm" color="primary" outlined
                                    wire:click="mountAction('restoreMaterial', { material: {{ $material['id'] }} })">
                                    {{ __('candidates.materials.actions.restore') }}
                                </x-filament::button>

                                @if ($material['can_retry'])
                                    <x-filament::button size="sm" color="gray" outlined
                                        wire:click="mountAction('retryMaterialPreparation', { material: {{ $material['id'] }} })">
                                        {{ __('candidates.materials.actions.retry') }}
                                    </x-filament::button>
                                @endif

                                <x-filament::button size="sm" color="danger" outlined
                                    wire:click="mountAction('deleteMaterial', { material: {{ $material['id'] }} })">
                                    {{ __('candidates.materials.actions.delete') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (count($applications) > 0)
        <div class="mt-4 border-t border-gray-100 pt-3 dark:border-white/5">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                {{ __('candidates.materials.application_documents') }}</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ __('candidates.materials.application_documents_description') }}</p>

            <div class="mt-2 flex flex-col gap-1">
                @foreach ($applications as $application)
                    <x-filament::link :href="$application['url'] . '?section=application'" icon="heroicon-m-arrow-right" icon-position="after"
                        size="sm">
                        {{ __('candidates.materials.application_documents_link', ['job' => $application['job']]) }}
                    </x-filament::link>
                @endforeach
            </div>
        </div>
    @endif
</x-filament::section>
