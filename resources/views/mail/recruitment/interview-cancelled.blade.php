<x-mail::message>
# Interview cancelled

Hi {{ $context->candidateName }},

Your interview for the {{ $context->jobTitle }} role, previously scheduled for {{ $context->formattedDate() }} at {{ $context->formattedTime() }}, has been cancelled.

The recruiting team will contact you if further action is needed.

Thanks,<br>
{{ $context->employerName }}
</x-mail::message>
