{{-- The body is recruiter-authored rich text whose variables were already
     resolved (and HTML-escaped) by App\Services\EmailTemplateRenderer. --}}
<x-mail::message>
{!! $body !!}
</x-mail::message>
