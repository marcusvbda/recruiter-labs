<x-mail::message>
# Interview scheduled

Hi {{ $context->candidateName }},

Your interview for the {{ $context->jobTitle }} role is scheduled for {{ $context->formattedDate() }} at {{ $context->formattedTime() }}.

@if ($context->meetingUrl)
<x-mail::button :url="$context->meetingUrl">
Join interview
</x-mail::button>
@endif

Thanks,<br>
{{ $context->employerName }}
</x-mail::message>
