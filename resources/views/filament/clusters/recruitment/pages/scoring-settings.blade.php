<x-filament-panels::page>
    @php
        $settings = $this->scoringSettings;
    @endphp

    <div class="grid gap-6" data-testid="scoring-settings">
        <section class="rl-settings-hero" aria-labelledby="scoring-settings-heading">
            <div class="absolute -top-20 -right-12 -z-10 size-64 rounded-full bg-cyan-300/20 blur-3xl"></div>
            <div class="absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-scale" class="size-4" />
                    {{ __('scoring.eyebrow') }}
                </span>
                <h2 id="scoring-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                    {{ __('scoring.heading') }}
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                    {{ __('scoring.description') }}
                </p>
            </div>
        </section>

        <section class="rl-settings-panel" aria-labelledby="scoring-weights-heading" data-testid="scoring-weights">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h3 id="scoring-weights-heading" class="text-lg font-semibold text-gray-950 dark:text-white">
                        {{ __('scoring.weights_heading') }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ __('scoring.weights_description') }}
                    </p>
                </div>
                <x-filament::button color="primary" wire:click="mountAction('updateScoringWeights')"
                    wire:loading.attr="disabled">
                    {{ __('scoring.update.action') }}
                </x-filament::button>
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                <x-filament::badge color="primary" size="lg">
                    {{ __('scoring.fields.referral_bonus') }}: +{{ $settings['referral_bonus_percentage'] }}%
                </x-filament::badge>
            </div>
        </section>
    </div>
</x-filament-panels::page>
