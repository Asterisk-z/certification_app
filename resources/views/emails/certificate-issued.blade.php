<x-mail::message>
# Congratulations, {{ $certificate->recipient->full_name }}!

You have been issued the certificate **{{ $certificate->template->name }}**.

- **Certificate number:** {{ $certificate->certificate_number }}
- **Issue date:** {{ $certificate->issue_date->format('d M Y') }}
@if ($certificate->expiry_date)
- **Valid until:** {{ $certificate->expiry_date->format('d M Y') }}
@endif

Your certificate is attached as a PDF.

<x-mail::button :url="$viewUrl">
View certificate online
</x-mail::button>

Anyone can confirm its authenticity at any time using the verification page:

<x-mail::button :url="$verifyUrl" color="success">
Verify this certificate
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
