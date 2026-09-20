{{-- What the recruiter's template looks like once its variables are resolved.
     Subject and body arrive already rendered against sample values by
     App\Services\EmailTemplateRenderer; the body is recruiter-authored rich
     text, so it is displayed as HTML. --}}
<div class="fi-section rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ __('email_templates.preview.subject') }}
    </p>
    <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
        {{ filled($subject) ? $subject : __('email_templates.preview.empty') }}
    </p>

    <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
        {{ __('email_templates.preview.body') }}
    </p>

    <div class="prose prose-sm mt-1 max-w-none text-gray-700 dark:prose-invert dark:text-gray-200">
        @if (filled($body))
            {!! $body !!}
        @else
            <p class="text-gray-400">{{ __('email_templates.preview.empty') }}</p>
        @endif
    </div>
</div>
