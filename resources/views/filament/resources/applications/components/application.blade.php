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
            :heading="__('applications.admin.sections.social_profiles')"
            icon="heroicon-o-link"
        >
            @forelse ($applicationDetails['socials'] as $social)
                <div class="flex items-center justify-between gap-3 border-b border-gray-100 py-2.5 last:border-0 dark:border-white/5">
                    <span class="text-sm font-medium text-gray-600 dark:text-gray-300">{{ $social['label'] }}</span>
                    @if ($social['url'])
                        <a
                            href="{{ $social['url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-primary-600 hover:text-primary-500 max-w-44 truncate text-sm font-semibold"
                        >
                            {{ $social['account'] }}
                        </a>
                    @else
                        <span class="max-w-44 truncate text-sm text-gray-950 dark:text-white">{{ $social['account'] }}</span>
                    @endif
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.empty.social_profiles') }}
                </p>
            @endforelse
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.sections.origin')"
            icon="heroicon-o-megaphone"
        >
            <dl class="rl-application-detail-list">
                <div>
                    <dt>{{ __('applications.admin.fields.source') }}</dt>
                    <dd>{{ $applicationDetails['origin']['source'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.referral') }}</dt>
                    <dd>{{ $applicationDetails['origin']['referral'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.submitted_ip') }}</dt>
                    <dd>{{ $applicationDetails['origin']['submitted_ip'] }}</dd>
                </div>
            </dl>

            @if (count($applicationDetails['origin']['utms']))
                <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {{ __('applications.admin.fields.utm_parameters') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($applicationDetails['origin']['utms'] as $utm)
                            <x-filament::badge color="gray">
                                {{ $utm['name'] }}: {{ $utm['value'] }}
                            </x-filament::badge>
                        @endforeach
                    </div>
                </div>
            @endif
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
