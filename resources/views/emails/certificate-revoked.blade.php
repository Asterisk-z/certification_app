<x-mail::message>
# Certificate revoked

Hello {{ $certificate->recipient->full_name }},

Your certificate **{{ $certificate->certificate_number }}** ({{ $certificate->template->name }})
has been revoked and is no longer valid.

@if ($reason)
**Reason:** {{ $reason }}
@endif

If you believe this is a mistake, please contact the issuing administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
