@php
    $currentLocale = $locales[$current] ?? $locales['en'];
@endphp

<div class="fi-topbar-item">
    <x-filament::dropdown placement="bottom-end">
        <x-slot name="trigger">
            <button
                type="button"
                class="fi-topbar-item-btn"
                title="{{ __('settings.fields.language') }}"
            >
                <span style="font-size: 1.125rem; line-height: 1;">{{ $currentLocale['flag'] }}</span>
            </button>
        </x-slot>

        <x-filament::dropdown.list>
            @foreach ($locales as $code => $locale)
                <x-filament::dropdown.list.item
                    tag="a"
                    :href="route('locale.switch', $code)"
                    :color="$code === $current ? 'primary' : 'gray'"
                >
                    {{ $locale['flag'] }} {{ $locale['label'] }}
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>
    </x-filament::dropdown>
</div>
