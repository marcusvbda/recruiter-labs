<div class="rl-application-header">
    <div class="rl-application-header__identity">
        <div class="rl-application-header__avatar" aria-hidden="true">
            {{ $header['candidate_initials'] }}
        </div>

        <div class="min-w-0">
            <h2 class="truncate text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">
                <a href="{{ $header['candidate_url'] }}" wire:navigate class="hover:text-primary-600">
                    {{ $header['candidate_name'] }}
                </a>
            </h2>
            <p class="mt-1 truncate text-sm text-gray-500 dark:text-gray-400">
                <a href="{{ $header['job_url'] }}" wire:navigate class="hover:text-primary-600">
                    {{ $header['job'] }}
                </a>
            </p>
        </div>
    </div>

    <div class="rl-application-header__state">
        <span class="rl-application-header__stage" style="--rl-status-color: {{ $header['status_color'] }}">
            <span class="rl-application-header__stage-dot" aria-hidden="true"></span>
            <span>
                <span class="rl-application-header__stage-caption">{{ __('applications.admin.fields.pipeline_status') }}</span>
                <span class="rl-application-header__stage-name">{{ $header['status'] }}</span>
            </span>
        </span>

        @if ($header['stage_role'] === 'hired')
            <x-filament::badge color="success">{{ __('statuses.badges.hired') }}</x-filament::badge>
        @elseif ($header['stage_role'] === 'closed')
            <x-filament::badge color="danger">{{ __('statuses.badges.closed') }}</x-filament::badge>
        @elseif ($header['stage_role'] === 'final_stage')
            <x-filament::badge color="warning">{{ __('statuses.badges.final_stage') }}</x-filament::badge>
        @endif

        @if ($header['score'] !== null)
            <x-filament::badge color="gray" icon="heroicon-m-chart-bar">
                {{ __('applications.admin.summary.fit_score', ['score' => $header['score']]) }}
            </x-filament::badge>
        @endif

        @if ($header['coverage'] !== null)
            <x-filament::badge color="gray" icon="heroicon-m-chart-pie">
                {{ __('applications.admin.summary.coverage_badge', ['coverage' => $header['coverage']]) }}
            </x-filament::badge>
        @endif

        @if ($header['needs_validation_count'] > 0)
            <x-filament::badge color="warning" icon="heroicon-m-question-mark-circle">
                {{ trans_choice('applications.admin.summary.needs_validation', $header['needs_validation_count'], ['count' => $header['needs_validation_count']]) }}
            </x-filament::badge>
        @endif

        @if ($header['analysis_status'] !== 'completed')
            <x-filament::badge :color="$header['analysis_color']" size="sm">
                {{ $header['analysis_label'] }}
            </x-filament::badge>
        @endif

        @if ($header['next_interview'])
            <x-filament::badge color="info" icon="heroicon-m-calendar-days">
                {{ __('applications.admin.summary.interview_on', ['date' => $header['next_interview']['scheduled_at']]) }}
            </x-filament::badge>
        @endif
    </div>
</div>
