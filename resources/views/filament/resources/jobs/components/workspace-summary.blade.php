<section class="rl-job-summary" aria-label="{{ __('jobs.workspace.summary_label') }}">
    <div class="rl-job-summary__head">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
            <x-filament::badge :color="$summary['state_color']">
                {{ $summary['state_label'] }}
            </x-filament::badge>

            @if ($summary['hiring']['target_reached'])
                {{-- Reaching the objective is stated, never acted on: pausing,
                     unpublishing or carrying on is the recruiter's call. --}}
                <x-filament::badge color="success" icon="heroicon-m-check-badge">
                    {{ __('jobs.workspace.hiring_target_reached') }}
                </x-filament::badge>
            @elseif ($summary['hiring']['remaining'] > 0 && $summary['hiring']['target'] > 1)
                <x-filament::badge color="gray">
                    {{ trans_choice('jobs.workspace.positions_remaining', $summary['hiring']['remaining'], ['count' => $summary['hiring']['remaining']]) }}
                </x-filament::badge>
            @endif

            @if ($summary['waiting'] > 0)
                <a href="{{ $summary['pipeline_board_url'] }}" wire:navigate>
                    <x-filament::badge color="warning" icon="heroicon-m-clock">
                        {{ trans_choice('jobs.progress.waiting_too_long', $summary['waiting'], ['count' => $summary['waiting']]) }}
                    </x-filament::badge>
                </a>
            @endif

            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('jobs.fields.pipeline') }}:
                <a href="{{ $summary['pipeline_url'] }}" class="font-medium text-gray-950 hover:text-primary-600 dark:text-white">
                    {{ $summary['pipeline_name'] }}
                </a>
            </span>

            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $summary['key'] }}</span>
        </div>

        <dl class="rl-job-summary__metrics">
            @foreach ($summary['metrics'] as $metric)
                <div>
                    <dt>{{ $metric['label'] }}</dt>
                    <dd @class([
                        'tabular-nums',
                        $metric['color'] => $metric['value'] > 0,
                        'text-gray-400 dark:text-gray-500' => $metric['value'] === 0,
                    ])>
                        {{ $metric['value'] }}@isset($metric['target'])<span class="text-base font-medium text-gray-400 dark:text-gray-500">/{{ $metric['target'] }}</span>@endisset
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="rl-job-summary__attention">
        <h3 class="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
            {{ __('attention.job_heading') }}
        </h3>

        @if (count($summary['attention']) === 0)
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ __('jobs.workspace.no_attention') }}
            </p>
        @else
            <ul class="mt-2 space-y-2">
                @foreach ($summary['attention'] as $item)
                    <li class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <x-filament::badge :color="$item['severity_color']" size="sm" :icon="$item['icon']">
                            {{ $item['title'] }}
                        </x-filament::badge>
                        <span class="text-gray-500 dark:text-gray-400">{{ $item['explanation'] }}</span>
                        <x-filament::link :href="$item['action_url']" size="sm" icon="heroicon-m-arrow-right" icon-position="after">
                            {{ $item['action_label'] }}
                        </x-filament::link>
                    </li>
                @endforeach
            </ul>

            @if ($summary['attention_hidden_count'] > 0)
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ trans_choice('attention.hidden', $summary['attention_hidden_count'], ['count' => $summary['attention_hidden_count']]) }}
                </p>
            @endif
        @endif
    </div>
</section>
