<x-filament::section :heading="__('candidates.view.profile')" icon="heroicon-o-user">
    <dl class="rl-application-detail-grid">
        <div>
            <dt>{{ __('candidates.fields.email') }}</dt>
            <dd>{{ $profile['email'] ?? __('applications.admin.not_provided') }}</dd>
        </div>
        <div>
            <dt>{{ __('candidates.fields.phone') }}</dt>
            <dd>{{ $profile['phone'] ?? __('applications.admin.not_provided') }}</dd>
        </div>
        <div>
            <dt>{{ __('candidates.fields.created_at') }}</dt>
            <dd>{{ $profile['created_at'] ?? '—' }}</dd>
        </div>
    </dl>

    @if (count($profile['socials']))
        <div class="mt-5 flex flex-wrap gap-2 border-t border-gray-200 pt-4 dark:border-white/10">
            @foreach ($profile['socials'] as $social)
                @if ($social['url'])
                    <x-filament::link :href="$social['url']" target="_blank" rel="noopener noreferrer" size="sm">
                        {{ $social['label'] }}
                    </x-filament::link>
                @else
                    <x-filament::badge color="gray">{{ $social['label'] }}: {{ $social['account'] }}</x-filament::badge>
                @endif
            @endforeach
        </div>
    @endif
</x-filament::section>
