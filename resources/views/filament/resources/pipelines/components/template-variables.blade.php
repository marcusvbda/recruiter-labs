{{-- Click a chip to copy its placeholder, so recruiters never have to memorise
     the template syntax. The placeholders arrive pre-built from
     App\Services\EmailTemplateRenderer: a literal `{{ … }}` written here would be
     compiled by Blade as a real echo instead of rendering as text. --}}
<div
    x-data="{ copied: null }"
    class="fi-section rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
>
    <p class="text-sm font-medium text-gray-950 dark:text-white">
        {{ __('statuses.variables.title') }}
    </p>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
        {{ __('statuses.variables.description') }}
    </p>

    <div class="mt-3 space-y-3">
        @foreach ($groups as $group => $placeholders)
            <div class="flex flex-wrap items-center gap-2">
                <span class="w-24 shrink-0 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __("statuses.variables.groups.{$group}") }}
                </span>

                @foreach ($placeholders as $token => $placeholder)
                    <button
                        type="button"
                        x-on:click="navigator.clipboard.writeText(@js($placeholder)); copied = @js($token); setTimeout(() => copied = null, 1500)"
                        class="fi-badge inline-flex items-center gap-1 rounded-md bg-white px-2 py-1 font-mono text-xs text-gray-700 ring-1 ring-gray-950/10 transition hover:bg-primary-50 hover:text-primary-700 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20 dark:hover:bg-primary-500/20"
                    >
                        <span x-show="copied !== @js($token)">{{ $placeholder }}</span>
                        <span x-show="copied === @js($token)" x-cloak class="text-success-600 dark:text-success-400">
                            {{ __('statuses.variables.copied') }}
                        </span>
                    </button>
                @endforeach
            </div>
        @endforeach
    </div>
</div>
