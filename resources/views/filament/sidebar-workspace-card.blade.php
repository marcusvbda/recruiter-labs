<aside class="rl-sidebar-workspace-card" data-testid="sidebar-workspace-card">
    <div class="rl-sidebar-workspace-card__glow" aria-hidden="true"></div>

    <div class="relative">
        <div class="flex items-start gap-3">
            <span class="rl-sidebar-workspace-card__icon">
                <x-filament::icon icon="heroicon-o-building-office-2" class="size-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[0.625rem] font-semibold tracking-widest text-blue-100 uppercase">
                    {{ __('settings.sidebar.workspace') }}
                </p>
                <p class="mt-1 truncate text-sm font-semibold text-white" title="{{ $companyName }}">
                    {{ $companyName }}
                </p>
            </div>

            <span class="rl-sidebar-workspace-card__plan">{{ $planName }}</span>
        </div>
        <div class="mt-4 grid gap-2">
            <a href="{{ $newJobUrl }}" class="rl-sidebar-workspace-card__primary-action">
                <x-filament::icon icon="heroicon-m-plus" class="size-4" />
                {{ __('settings.sidebar.new_job') }}
            </a>

            <a href="{{ $settingsUrl }}" class="rl-sidebar-workspace-card__secondary-action">
                <x-filament::icon icon="heroicon-m-cog-6-tooth" class="size-4" />
                {{ __('settings.sidebar.manage_workspace') }}
            </a>
        </div>
    </div>
</aside>
