<x-filament::section
    :heading="__('communications.history.heading')"
    icon="heroicon-o-envelope"
    @if ($communications['has_in_flight_delivery']) wire:poll.3s="$refresh" @endif
>
    <x-slot name="description">{{ __('communications.history.application_description') }}</x-slot>

    @if ($communications['is_do_not_contact'])
        <p class="mb-3 text-sm text-warning-700 dark:text-warning-300">{{ __('communications.dnc.active_description') }}</p>
    @endif

    @if ($communications['messages'] === [])
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('communications.history.empty_for_application') }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($communications['messages'] as $message)
                <li class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-medium text-gray-950 dark:text-white">{{ $message['subject'] ?: __('communications.history.untitled_draft') }}</span>
                    <x-filament::badge :color="match ($message['status']) { 'sent' => 'success', 'failed', 'ambiguous' => 'danger', 'queued', 'sending' => 'warning', default => 'gray' }">{{ $message['status_label'] }}</x-filament::badge>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $message['ai_assisted'] ? __('communications.history.ai_assisted') : __('communications.history.manual') }}
                        @if ($message['authorized_by']) · {{ __('communications.history.authorized_by', ['name' => $message['authorized_by']]) }} @endif
                        @if ($message['sent_at']) · {{ $message['sent_at'] }} @endif
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mt-4">
        <x-filament::button tag="a" :href="$communications['candidate_url']" icon="heroicon-m-envelope" size="sm">
            {{ __('communications.actions.open_candidate_communications', ['candidate' => $communications['candidate_name']]) }}
        </x-filament::button>
    </div>
</x-filament::section>
