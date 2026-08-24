{{--
    One criterion's result. The two groups on the evaluation tab render the same
    row, so a criterion cannot describe itself differently depending on which
    list it happens to land in.

    A criterion with no fit score shows no number and no bar: the application did
    not support a judgement, and drawing an empty progress bar would read as a
    zero.
--}}
<div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $criterionScore['criterion'] }}</p>
            <div class="mt-2 flex flex-wrap gap-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                <span>{{ __('applications.admin.ai.criteria.weight_label', ['weight' => $criterionScore['weight']]) }}</span>
                <span>·</span>
                <span>{{ __('applications.admin.ai.criteria.importance_label') }} {{ __("applications.admin.ai.criteria.importance.{$criterionScore['importance']}") }}</span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if ($criterionScore['is_assessed'])
                <x-filament::badge color="gray">{{ __('applications.admin.ai.criteria.fit_label', ['score' => $criterionScore['score']]) }}</x-filament::badge>
            @else
                <x-filament::badge color="gray" icon="heroicon-o-question-mark-circle">
                    {{ __('applications.admin.ai.criteria.not_assessed') }}
                </x-filament::badge>
            @endif

            <x-filament::badge :color="$confidenceColor" :icon="$confidenceIcon">
                {{ __('applications.admin.ai.confidence_label') }} {{ __("applications.admin.ai.confidence.{$criterionScore['confidence']}") }}
            </x-filament::badge>
        </div>
    </div>

    @if ($criterionScore['is_assessed'])
        <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
            <div class="h-full rounded-full bg-gray-500 dark:bg-gray-400" style="width: {{ $criterionScore['score'] }}%"></div>
        </div>
    @endif

    <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $criterionScore['reason'] }}</p>

    @if ($criterionScore['evidence'] !== [])
        <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
            {{ __('applications.admin.ai.evidence.heading') }}</p>
        <ul class="mt-1 space-y-1 text-sm leading-6 text-gray-600 dark:text-gray-300">
            @foreach ($criterionScore['evidence'] as $evidence)
                <li class="flex gap-2">
                    <span class="shrink-0 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500" style="padding-block-start: 0.3rem;">
                        {{ $evidence['source'] }}
                    </span>
                    <span>{{ $evidence['detail'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
