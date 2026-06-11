<x-mail::message>
# Congratulations, {{ $certificate->recipient->full_name }}!

You have been awarded the credential **{{ $certificate->displayName() ?? $certificate->certificate_number }}**.

<x-mail::panel>
**Awarded to:** {{ $certificate->recipient->full_name }}<br>
**Credential number:** {{ $certificate->certificate_number }}<br>
@if ($certificate->completion_date)
**Completion date:** {{ $certificate->completion_date->format('d M Y') }}<br>
@endif
**Issue date:** {{ $certificate->issue_date->format('d M Y') }}<br>
@if ($certificate->expiry_date)
**Valid until:** {{ $certificate->expiry_date->format('d M Y') }}<br>
@endif
@foreach ($certificate->data ?? [] as $field => $value)
@if (filled($value))
**{{ \Illuminate\Support\Str::headline($field) }}:** {{ $value }}<br>
@endif
@endforeach
</x-mail::panel>

Your credential is attached as a **PDF** and as a **PNG image** you can share.

<x-mail::button :url="$viewUrl">
View credential online
</x-mail::button>

Anyone can confirm its authenticity at any time using the verification page:

<x-mail::button :url="$verifyUrl" color="success">
Verify this credential
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
