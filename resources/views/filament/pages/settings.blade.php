<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                {{ __('settings.actions.save') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
