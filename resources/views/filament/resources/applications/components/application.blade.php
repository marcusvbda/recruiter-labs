<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <x-filament::section
            :heading="__('applications.admin.sections.answers')"
            :description="__('applications.admin.sections.answers_description')"
            icon="heroicon-o-chat-bubble-left-right"
        >
            @forelse ($applicationDetails['answers'] as $answer)
                <article class="rl-application-answer">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-semibold text-gray-950 dark:text-white">{{ $answer['question'] }}</h3>
                        <x-filament::badge color="gray">{{ $answer['type'] }}</x-filament::badge>
                    </div>
                    <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-gray-700 dark:text-gray-200">{{ $answer['value'] }}</p>
                </article>
            @empty
                <x-filament::empty-state
                    :contained="false"
                    :heading="__('applications.admin.empty.answers')"
                    icon="heroicon-o-chat-bubble-left-ellipsis"
                    icon-color="gray"
                />
            @endforelse
        </x-filament::section>
    </div>

    <div class="space-y-6">
        <x-filament::section
            :heading="__('applications.admin.sections.submitted_information')"
            icon="heroicon-o-identification"
        >
            <dl class="rl-application-detail-list">
                <div>
                    <dt>{{ __('applications.admin.fields.name') }}</dt>
                    <dd>{{ $applicationDetails['candidate_name'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.email') }}</dt>
                    <dd>{{ $applicationDetails['candidate_email'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.phone') }}</dt>
                    <dd>{{ $applicationDetails['candidate_phone'] }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.sections.cover_letter')"
            icon="heroicon-o-document-text"
        >
            <x-filament::badge color="gray">{{ $applicationDetails['cover_letter_type'] }}</x-filament::badge>

            @if ($applicationDetails['cover_letter_text'])
                <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-gray-700 dark:text-gray-200">
                    {{ $applicationDetails['cover_letter_text'] }}
                </p>
            @else
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.cover_letter.no_text') }}
                </p>
            @endif
        </x-filament::section>
    </div>
</div>
