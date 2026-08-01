<section class="relative isolate overflow-hidden rounded-3xl bg-linear-to-br from-primary-700 via-primary-600 to-cyan-500 px-6 py-7 text-white shadow-lg shadow-primary-900/10 sm:px-8 sm:py-9">
    <div class="absolute -top-28 -right-20 -z-10 size-72 rounded-full bg-white/15 blur-3xl"></div>
    <div class="absolute -bottom-36 left-1/3 -z-10 size-80 rounded-full bg-cyan-300/20 blur-3xl"></div>

    <div class="flex flex-col gap-7 lg:flex-row lg:items-center lg:justify-between">
        <div class="max-w-3xl">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold tracking-wide uppercase backdrop-blur-sm">
                    <span @class([
                        'size-2 rounded-full',
                        'bg-emerald-300' => $job->published,
                        'bg-amber-300' => ! $job->published,
                    ])></span>
                    {{ $job->published ? __('jobs.dashboard.published') : __('jobs.dashboard.draft') }}
                </span>
                <span class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-medium backdrop-blur-sm">
                    <x-filament::icon icon="heroicon-o-key" class="size-3.5" />
                    {{ $job->key }}
                </span>
            </div>

            <p class="mt-6 text-sm font-semibold tracking-[0.18em] text-cyan-100 uppercase">
                {{ __('jobs.dashboard.eyebrow') }}
            </p>
            <h2 class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                {{ $job->name }}
            </h2>
            <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-50 sm:text-base">
                {{ __('jobs.dashboard.introduction') }}
            </p>
        </div>

        <a
            href="{{ $publicUrl }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-white px-5 py-3 text-sm font-semibold text-primary-700 shadow-lg shadow-primary-950/10 transition hover:-translate-y-0.5 hover:bg-blue-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
        >
            <x-filament::icon icon="heroicon-o-arrow-top-right-on-square" class="size-5" />
            {{ __('jobs.dashboard.open_public_page') }}
        </a>
    </div>
</section>
