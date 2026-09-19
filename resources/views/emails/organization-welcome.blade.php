<x-mail::message>
# Welcome, {{ $organization->name }}

An account has been created for **{{ $organization->name }}** on **{{ config('mail.brand_name') }}**,
where you can design templates, manage recipients and issue credentials for your organization.

Your login email is **{{ $organization->email }}**.

@if ($setupLinkSent)
We've sent you a separate email with a link to set your password. Once that's done,
sign in to get started.
@else
Sign in with the email above and the password provided by your administrator.
@endif

<x-mail::button :url="$loginUrl">
Go to {{ config('mail.brand_name') }}
</x-mail::button>

Thanks,<br>
{{ config('mail.brand_name') }}
</x-mail::message>
