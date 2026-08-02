<div class="rl-application-header">
    <div class="rl-application-header__identity">
        <div class="rl-application-header__avatar" aria-hidden="true">
            {{ $header['candidate_initials'] }}
        </div>

        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="truncate text-xl font-bold text-gray-950 sm:text-2xl dark:text-white">
                    {{ $header['candidate_name'] }}
                </h2>
                <x-filament::badge :color="$header['status_color']">
                    {{ $header['status'] }}
                </x-filament::badge>
                <x-filament::badge :color="$header['analysis_color']" icon="heroicon-m-sparkles">
                    {{ $header['analysis_label'] }}
                </x-filament::badge>
            </div>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $header['job'] }}
            </p>
        </div>
    </div>

    <dl class="rl-application-header__facts">
        <div>
            <dt>{{ __('applications.admin.fields.email') }}</dt>
            <dd>{{ $header['email'] }}</dd>
        </div>
        <div>
            <dt>{{ __('applications.admin.fields.phone') }}</dt>
            <dd>{{ $header['phone'] }}</dd>
        </div>
        <div>
            <dt>{{ __('applications.admin.fields.applied_at') }}</dt>
            <dd>{{ $header['applied_at'] }}</dd>
        </div>
        <div>
            <dt>{{ __('applications.admin.fields.source') }}</dt>
            <dd>{{ $header['source'] }}</dd>
        </div>
        <div>
            <dt>{{ __('applications.admin.fields.referral') }}</dt>
            <dd>{{ $header['referral'] }}</dd>
        </div>
    </dl>
</div>
