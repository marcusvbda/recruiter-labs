<x-filament-panels::page>
    <div class="grid gap-6" data-testid="email-notification-settings">
        <section class="rl-settings-hero" aria-labelledby="email-notification-settings-heading">
            <div class="absolute -top-20 -right-12 -z-10 size-64 rounded-full bg-cyan-300/20 blur-3xl"></div>
            <div class="absolute -bottom-32 left-1/4 -z-10 size-80 rounded-full bg-white/15 blur-3xl"></div>
            <div class="relative max-w-3xl">
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="size-4" />
                    Recruitment communication
                </span>
                <h2 id="email-notification-settings-heading" class="mt-4 text-2xl font-bold tracking-tight sm:text-3xl">
                    Keep candidates informed automatically
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                    Control the native emails RecruiterLabs sends throughout your recruitment process. Email content and
                    delivery events are maintained by RecruiterLabs.
                </p>
            </div>
        </section>

        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" wire:loading.attr="disabled" wire:target="save">
                    Save changes
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
