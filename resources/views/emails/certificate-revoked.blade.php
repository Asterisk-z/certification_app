<x-mail::message>
# Credential revoked

Hello {{ $certificate->recipient->full_name }},

Your credential **{{ $certificate->certificate_number }}** ({{ $certificate->displayName() }})
has been revoked and is no longer valid.

@if ($reason)
**Reason:** {{ $reason }}
@endif

If you believe this is a mistake, please contact the issuing administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
