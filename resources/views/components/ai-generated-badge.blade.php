@props([
    'assisted' => false,
])

{{-- Section-level AI provenance. One marker per section that is materially
     AI-produced — never one per sentence. --}}
<x-filament::badge
    color="info"
    icon="heroicon-m-sparkles"
    :tooltip="__('ai_activity.provenance.tooltip')"
>
    {{ $assisted ? __('ai_activity.provenance.assisted') : __('ai_activity.provenance.generated') }}
</x-filament::badge>
