<x-filament::section
    :heading="__('applications.admin.sections.documents')"
    :description="__('applications.admin.sections.documents_description')"
    icon="heroicon-o-folder-open"
>
    @if (count($documents))
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($documents as $document)
                <article class="rl-application-document">
                    <div class="flex items-start gap-4">
                        <div class="rl-application-document__icon">
                            <x-filament::icon icon="heroicon-o-document" class="size-6" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-semibold text-gray-950 dark:text-white">
                                    {{ $document['original_name'] }}
                                </h3>
                                <x-filament::badge color="primary">{{ $document['type'] }}</x-filament::badge>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ $document['extension'] }} · {{ $document['size'] }}
                            </p>
                        </div>
                    </div>

                    <dl class="mt-4 grid gap-3 border-t border-gray-200 pt-4 text-sm sm:grid-cols-2 dark:border-white/10">
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                {{ __('applications.admin.documents.mime_type') }}
                            </dt>
                            <dd class="mt-1 break-all text-gray-800 dark:text-gray-200">{{ $document['mime_type'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                                {{ __('applications.admin.documents.uploaded_at') }}
                            </dt>
                            <dd class="mt-1 text-gray-800 dark:text-gray-200">{{ $document['uploaded_at'] }}</dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-2">
                        @if ($document['can_preview'])
                            <x-filament::button
                                tag="a"
                                :href="$document['view_url']"
                                target="_blank"
                                rel="noopener noreferrer"
                                icon="heroicon-m-eye"
                                color="gray"
                                outlined
                            >
                                {{ __('applications.admin.actions.view_document') }}
                            </x-filament::button>
                        @endif
                        <x-filament::button
                            tag="a"
                            :href="$document['download_url']"
                            icon="heroicon-m-arrow-down-tray"
                        >
                            {{ __('applications.admin.actions.download_document') }}
                        </x-filament::button>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        <x-filament::empty-state
            :contained="false"
            :heading="__('applications.admin.empty.documents')"
            :description="__('applications.admin.empty.documents_description')"
            icon="heroicon-o-document-minus"
            icon-color="gray"
        />
    @endif
</x-filament::section>
