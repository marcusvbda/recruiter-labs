<div class="grid gap-5">
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
        <div class="rounded-xl bg-gray-50 p-4 dark:bg-white/5">
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('settings.plan.change.current') }}</p>
            <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $comparison['current_plan'] }}</p>
        </div>
        <span class="flex size-8 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
            <x-filament::icon icon="heroicon-m-arrow-right" class="size-4" />
        </span>
        <div class="rounded-xl bg-primary-50 p-4 dark:bg-primary-500/10">
            <p class="text-xs font-medium text-primary-600 dark:text-primary-300">{{ __('settings.plan.change.new') }}</p>
            <p class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $comparison['new_plan'] }}</p>
        </div>
    </div>

    <div>
        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('settings.plan.change.changes') }}</p>
        <dl class="mt-3 grid gap-2">
            @foreach ($comparison['limit_changes'] as $change)
                <div class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                    <dt class="text-gray-600 dark:text-gray-300">{{ $change['label'] }}</dt>
                    <dd class="flex items-center gap-2 font-semibold text-gray-950 dark:text-white">
                        <span class="text-gray-400 line-through">{{ $change['from'] }}</span>
                        <x-filament::icon icon="heroicon-m-arrow-right" class="size-3.5 text-gray-400" />
                        <span>{{ $change['to'] }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if ($comparison['added_features'] !== [] || $comparison['removed_features'] !== [])
        <div class="grid gap-3 sm:grid-cols-2">
            @if ($comparison['added_features'] !== [])
                <div class="rounded-xl border border-success-200 bg-success-50/60 p-4 dark:border-success-500/20 dark:bg-success-500/10">
                    <p class="text-sm font-semibold text-success-800 dark:text-success-200">{{ __('settings.plan.change.features_added') }}</p>
                    <ul class="mt-2 grid gap-1 text-sm text-success-700 dark:text-success-300">
                        @foreach ($comparison['added_features'] as $feature)
                            <li class="flex items-center gap-2"><x-filament::icon icon="heroicon-m-check" class="size-4" />{{ $feature }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($comparison['removed_features'] !== [])
                <div class="rounded-xl border border-warning-200 bg-warning-50/60 p-4 dark:border-warning-500/20 dark:bg-warning-500/10">
                    <p class="text-sm font-semibold text-warning-800 dark:text-warning-200">{{ __('settings.plan.change.features_removed') }}</p>
                    <ul class="mt-2 grid gap-1 text-sm text-warning-700 dark:text-warning-300">
                        @foreach ($comparison['removed_features'] as $feature)
                            <li class="flex items-center gap-2"><x-filament::icon icon="heroicon-m-minus" class="size-4" />{{ $feature }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif

    <div class="rounded-xl border border-primary-200 bg-primary-50/70 p-4 text-sm text-primary-800 dark:border-primary-500/20 dark:bg-primary-500/10 dark:text-primary-200">
        <p class="flex items-start gap-2"><x-filament::icon icon="heroicon-o-bolt" class="mt-0.5 size-4 shrink-0" />{{ __('settings.plan.change.immediate') }}</p>
        <p class="mt-2 flex items-start gap-2"><x-filament::icon icon="heroicon-o-credit-card" class="mt-0.5 size-4 shrink-0" />{{ __('settings.plan.change.no_charge') }}</p>
    </div>
</div>
