<x-filament::section
    :heading="__('communications.history.heading')"
    icon="heroicon-o-envelope"
    @if ($has_in_flight_delivery) wire:poll.3s="$refresh" @endif
>
    <x-slot name="description">
        {{ $context_job
            ? __('communications.history.context_description', ['job' => $context_job])
            : __('communications.history.description') }}
    </x-slot>

    @if ($is_do_not_contact)
        <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            {{ __('communications.dnc.active_description') }}
        </div>
    @elseif (! $has_valid_email)
        <div class="mb-4 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-700 dark:bg-warning-950 dark:text-warning-100">
            {{ __('communications.recipient.invalid_email') }}
        </div>
    @endif

    @if ($threads === [])
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('communications.history.empty') }}</p>
    @else
        <div class="space-y-5">
            @foreach ($threads as $thread)
                <div class="border-t border-gray-200 pt-4 first:border-t-0 first:pt-0 dark:border-white/10">
                    @if ($thread['job'])
                        <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ $thread['job'] }}</p>
                    @endif

                    <ol class="space-y-3">
                        @foreach ($thread['messages'] as $message)
                            <li class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $message['subject'] ?: __('communications.history.untitled_draft') }}</span>
                                    <x-filament::badge :color="match ($message['status']) { 'sent' => 'success', 'failed', 'ambiguous' => 'danger', 'queued', 'sending' => 'warning', default => 'gray' }">
                                        {{ $message['status_label'] }}
                                    </x-filament::badge>
                                    @if ($message['ai_assisted'])
                                        <x-filament::badge color="info">{{ __('communications.history.ai_assisted') }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="gray">{{ __('communications.history.manual') }}</x-filament::badge>
                                    @endif
                                </div>

                                @if ($message['body'])
                                    <p class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $message['body'] }}</p>
                                @endif

                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($message['authorized_by'])
                                        {{ __('communications.history.authorized_by', ['name' => $message['authorized_by']]) }}
                                    @endif
                                    @if ($message['sent_at'])
                                        @if ($message['authorized_by']) · @endif{{ $message['sent_at'] }}
                                    @endif
                                    @if ($message['sender'] || $message['recipient'])
                                        · {{ __('communications.history.direction', ['sender' => $message['sender'] ?: '—', 'recipient' => $message['recipient'] ?: '—']) }}
                                    @endif
                                </p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        </div>
    @endif

    @if ($context_job)
        <div class="mt-4">
            <x-filament::button wire:click="mountAction('composeCommunication')" icon="heroicon-m-envelope" size="sm" :disabled="$is_do_not_contact">
                {{ __('communications.actions.contact_candidate') }}
            </x-filament::button>
        </div>
    @endif
</x-filament::section>
