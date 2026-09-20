@props([
    'item',
    'color' => 'gray',
])

{{-- One line of AI work. It links into the Job or Application it is about
     whenever a meaningful destination exists, and stays a plain row when it
     does not — a dead link is worse than no link. --}}
@php
    $tag = $item['url'] ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($item['url']) href="{{ $item['url'] }}" @endif
    class="flex flex-col gap-1 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10 @if ($item['url']) transition hover:bg-gray-50 dark:hover:bg-white/5 @endif"
>
    <div class="flex flex-wrap items-center gap-2">
        <x-filament::badge :color="$color">{{ $item['role'] }}</x-filament::badge>

        <span class="text-sm text-gray-950 dark:text-white">{{ $item['description'] }}</span>
    </div>

    @if ($item['reason'])
        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $item['reason'] }}</span>
    @endif
</{{ $tag }}>
