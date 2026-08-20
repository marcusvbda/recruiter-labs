<x-filament::section
    :heading="__('candidates.view.applications')"
    :description="__('candidates.view.applications_description')"
    icon="heroicon-o-briefcase"
>
    @forelse ($applications as $application)
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 py-3 first:pt-0 last:border-0 last:pb-0 dark:border-white/5">
            <div class="min-w-0">
                <a href="{{ $application['job_url'] }}" wire:navigate class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">
                    {{ $application['job'] }}
                </a>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.fields.applied_at') }}: {{ $application['applied_at'] }}
                    @if ($application['next_interview'])
                        · {{ __('candidates.view.next_interview', ['date' => $application['next_interview']]) }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 dark:text-gray-300">
                    <span class="size-2 rounded-full" style="background-color: {{ $application['status_color'] }}"></span>
                    {{ $application['status'] }}
                </span>

                @if ($application['stage_age'])
                    <span @class([
                        'text-xs',
                        'font-medium text-warning-600 dark:text-warning-400' => $application['is_overdue'],
                        'text-gray-500 dark:text-gray-400' => ! $application['is_overdue'],
                    ])>
                        {{ $application['stage_age'] }}
                    </span>
                @endif

                @if ($application['stage_role'] === 'hired')
                    <x-filament::badge color="success" size="sm">{{ __('statuses.badges.hired') }}</x-filament::badge>
                @elseif ($application['stage_role'] === 'closed')
                    <x-filament::badge color="danger" size="sm">{{ __('statuses.badges.closed') }}</x-filament::badge>
                @elseif ($application['stage_role'] === 'final_stage')
                    <x-filament::badge color="warning" size="sm">{{ __('statuses.badges.final_stage') }}</x-filament::badge>
                @endif

                @if ($application['score'] !== null)
                    <x-filament::badge color="gray" size="sm">
                        {{ __('candidates.view.fit', ['score' => $application['score']]) }}
                    </x-filament::badge>
                @endif

                <x-filament::link :href="$application['url']" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
                    {{ __('applications.admin.actions.view_application') }}
                </x-filament::link>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('candidates.view.no_applications') }}</p>
    @endforelse
</x-filament::section>
