{{-- The body is recruiter-authored content whose template variables were
     already resolved (and HTML-escaped) by App\Services\EmailTemplateRenderer.
     It is sanitized again here so a snapshot written before the composer reset
     — or by any other caller — can never reach a candidate as executable
     markup, and so plain text keeps its line breaks. --}}
<x-mail::message>
{!! \App\Support\CandidateMessageBody::toStoredHtml($body) !!}
</x-mail::message>
