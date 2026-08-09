<x-mail::message>
# Interview rescheduled

Hi {{ $context->candidateName }},

Your interview for the {{ $context->jobTitle }} role has been rescheduled to {{ $context->formattedDate() }} at {{ $context->formattedTime() }}.

@if ($context->meetingUrl)
<x-mail::button :url="$context->meetingUrl">
Join interview
</x-mail::button>
@endif

Thanks,<br>
{{ $context->employerName }}
</x-mail::message>
