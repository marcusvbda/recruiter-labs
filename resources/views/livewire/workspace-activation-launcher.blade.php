{{--
    Floating, secondary access to the same activation journey the Overview
    checklist shows. Fixed position, rendered at the very end of the body, so it
    never sits inside the topbar, sidebar or a page's own action bar — those keep
    whatever space they already had.

    Livewire requires a single root element in every state, including the states
    where this surface deliberately shows nothing: an activated workspace, or a
    user who hid it. So the root is an unstyled wrapper that is always present
    and the floating card lives inside it. An empty wrapper renders nothing and,
    carrying no position or size of its own, cannot cover a product control.
--}}
<div>
    @if ($visible)
        <div class="rl-launcher" role="complementary" aria-label="{{ __('onboarding.launcher.label') }}">
            <div class="rl-launcher-card">
                <button
                    type="button"
                    wire:click="toggle"
                    class="rl-launcher-toggle"
                    aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                    aria-controls="rl-launcher-panel"
                >
                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="rl-launcher-toggle__icon" aria-hidden="true" />

                    <span class="rl-launcher-toggle__body">
                        <span class="rl-launcher-toggle__label">{{ __('onboarding.launcher.label') }}</span>
                        <span class="rl-launcher-toggle__count">
                            {{ __('onboarding.launcher.progress', ['done' => $completedCount, 'total' => $totalCount]) }}
                        </span>
                    </span>

                    <x-filament::icon
                        :icon="$expanded ? 'heroicon-m-chevron-down' : 'heroicon-m-chevron-up'"
                        class="rl-launcher-toggle__chevron"
                        aria-hidden="true"
                    />
                </button>

                @if ($expanded)
                    <div id="rl-launcher-panel" class="rl-launcher-panel">
                        <ul class="rl-launcher-list">
                            @foreach ($primarySteps as $step)
                                <li class="rl-launcher-item" data-complete="{{ $step['is_complete'] ? 'true' : 'false' }}">
                                    <x-filament::icon
                                        :icon="$step['is_complete'] ? 'heroicon-m-check-circle' : 'heroicon-o-check-circle'"
                                        class="rl-launcher-item__icon"
                                        aria-hidden="true"
                                    />

                                    <span class="rl-launcher-item__title">
                                        {{ __('onboarding.checklist.steps.' . $step['key'] . '.title') }}
                                    </span>

                                    @if ($step['url'])
                                        <a
                                            href="{{ $step['url'] }}"
                                            wire:navigate
                                            class="rl-launcher-item__action"
                                            @class(['rl-launcher-item__action--primary' => $step['key'] === $nextStepKey])
                                        >
                                            {{ __('onboarding.checklist.steps.' . $step['key'] . '.action') }}
                                        </a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <div class="rl-launcher-footer">
                            <a href="{{ $overviewUrl }}" wire:navigate class="rl-launcher-footer__link">
                                {{ __('onboarding.launcher.view_checklist') }}
                            </a>

                            <button type="button" wire:click="hide" class="rl-launcher-footer__hide">
                                {{ __('onboarding.launcher.hide') }}
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
