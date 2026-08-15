<div class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        <x-filament::section
            :heading="__('applications.admin.sections.candidate')"
            icon="heroicon-o-user"
        >
            <dl class="rl-application-detail-grid">
                <div>
                    <dt>{{ __('applications.admin.fields.name') }}</dt>
                    <dd>{{ $overview['candidate']['name'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.email') }}</dt>
                    <dd>{{ $overview['candidate']['email'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.phone') }}</dt>
                    <dd>{{ $overview['candidate']['phone'] }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.sections.recruitment')"
            icon="heroicon-o-briefcase"
        >
            <dl class="rl-application-detail-grid">
                <div>
                    <dt>{{ __('applications.admin.fields.job') }}</dt>
                    <dd>{{ $overview['recruitment']['job'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.pipeline_status') }}</dt>
                    <dd>{{ $overview['recruitment']['status'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.applied_at') }}</dt>
                    <dd>{{ $overview['recruitment']['applied_at'] }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.overview.overall_score.heading')"
            icon="heroicon-o-scale"
        >
            @if ($overview['overall_score'] === null)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ __('applications.admin.overview.overall_score.not_available') }}
                </p>
            @else
                @php
                    $overallScore = $overview['overall_score'];
                @endphp
                <div class="flex items-center gap-4">
                    <p class="text-4xl font-bold {{ $overallScore['value'] >= 70 ? 'text-success-600 dark:text-success-400' : ($overallScore['value'] >= 40 ? 'text-warning-600 dark:text-warning-400' : 'text-danger-600 dark:text-danger-400') }}">
                        {{ $overallScore['value'] }}/100
                    </p>
                </div>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    {{ __('applications.admin.overview.overall_score.ai_component', [
                        'score' => $overallScore['ai_score'],
                    ]) }}
                    ·
                    {{ __(
                        $overallScore['is_referral']
                            ? 'applications.admin.overview.overall_score.referral_yes'
                            : 'applications.admin.overview.overall_score.referral_no',
                        ['bonus' => $overallScore['referral_bonus_percentage']],
                    ) }}
                    @if ($overallScore['is_capped'])
                        · {{ __('applications.admin.overview.overall_score.capped') }}
                    @endif
                </p>
            @endif
        </x-filament::section>
    </div>

    <div class="space-y-6">
        <x-filament::section
            :heading="__('applications.admin.sections.origin')"
            icon="heroicon-o-megaphone"
        >
            <dl class="rl-application-detail-list">
                <div>
                    <dt>{{ __('applications.admin.fields.source') }}</dt>
                    <dd>{{ $overview['origin']['source'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.referral') }}</dt>
                    <dd>{{ $overview['origin']['referral'] }}</dd>
                </div>
                <div>
                    <dt>{{ __('applications.admin.fields.submitted_ip') }}</dt>
                    <dd>{{ $overview['origin']['submitted_ip'] }}</dd>
                </div>
            </dl>

            @if (count($overview['origin']['utms']))
                <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                    <p class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                        {{ __('applications.admin.fields.utm_parameters') }}
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($overview['origin']['utms'] as $utm)
                            <x-filament::badge color="gray">
                                {{ $utm['name'] }}: {{ $utm['value'] }}
                            </x-filament::badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section
            :heading="__('applications.admin.sections.social_profiles')"
            icon="heroicon-o-link"
        >
            @forelse ($overview['candidate']['socials'] as $social)
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
    </div>
</div>
