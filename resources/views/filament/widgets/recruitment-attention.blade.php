<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('attention.heading')"
        :description="__('attention.description')"
        icon="heroicon-o-inbox-stack"
    >
        @if (count($items) === 0)
            {{-- A clear queue is information too: the recruiter should be able to
                 tell "nothing is broken" apart from "nothing loaded". --}}
            <div class="rl-attention-empty">
                <x-filament::icon icon="heroicon-o-check-circle" class="size-5 text-success-500" />
                <div>
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ __('attention.empty_heading') }}</p>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ __('attention.empty_description') }}</p>
                </div>
            </div>
        @else
            <ul class="rl-attention-list">
                @foreach ($items as $item)
                    <li class="rl-attention-item" data-severity="{{ $item['severity'] }}">
                        <span class="rl-attention-item__icon" aria-hidden="true">
                            <x-filament::icon :icon="$item['icon']" class="size-4" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $item['title'] }}
                                </span>
                                <x-filament::badge :color="$item['severity_color']" size="sm">
                                    {{ __('attention.severities.' . $item['severity']) }}
                                </x-filament::badge>
                                @if ($item['context'])
                                    <span class="truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item['context'] }}
                                    </span>
                                @endif
                            </div>

                            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">
                                {{ $item['explanation'] }}
                            </p>
                        </div>

                        <div class="rl-attention-item__action">
                            <x-filament::button
                                :href="$item['action_url']"
                                tag="a"
                                size="sm"
                                color="gray"
                                icon="heroicon-m-arrow-right"
                                icon-position="after"
                            >
                                {{ $item['action_label'] }}
                            </x-filament::button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($hidden_count > 0)
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    {{ trans_choice('attention.hidden', $hidden_count, ['count' => $hidden_count]) }}
                </p>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
