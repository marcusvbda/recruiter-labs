<div>
    <section id="utm-ranking" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-4 dark:border-white/10">
            <span class="flex size-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-megaphone" class="size-5" />
            </span>
            <div>
                <h3 class="font-semibold text-gray-950 dark:text-white">{{ __('jobs.analytics.utm_ranking.title') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('jobs.analytics.utm_ranking.description') }}</p>
            </div>
        </div>

        @if ($utmRanking->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                {{ __('jobs.analytics.utm_ranking.empty') }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-semibold tracking-wide text-gray-500 uppercase dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3">{{ __('jobs.analytics.utm_ranking.parameter') }}</th>
                            <th class="px-5 py-3">{{ __('jobs.analytics.utm_ranking.value') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('jobs.analytics.clicks') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($utmRanking as $position => $item)
                            <tr>
                                <td class="px-5 py-3 font-medium text-gray-950 dark:text-white">
                                    <span class="mr-2 text-xs text-gray-400">#{{ $utmRanking->firstItem() + $position }}</span>{{ $item['name'] }}
                                </td>
                                <td class="max-w-52 truncate px-5 py-3 text-gray-600 dark:text-gray-300">{{ $item['value'] }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-primary-600 dark:text-primary-400">{{ number_format($item['clicks']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($utmRanking->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-white/10">
                    {{ $utmRanking->onEachSide(1)->links() }}
                </div>
            @endif
        @endif
    </section>

    <script>
        if (window.location.hash === '#utm-ranking') {
            window.addEventListener('load', () => {
                document.getElementById('utm-ranking')?.scrollIntoView({ block: 'start' });
            });
        }
    </script>
</div>
