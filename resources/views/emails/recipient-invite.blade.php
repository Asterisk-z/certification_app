<x-mail::message>
# Hello {{ $recipient->full_name }},

You have credentials waiting for you on **{{ config('mail.brand_name') }}**.

Create a password to access your personal dashboard, where you can view,
verify and download all credentials issued to you.

<x-mail::button :url="$inviteUrl">
Create my password
</x-mail::button>

This link is personal and expires in 7 days.

Thanks,<br>
{{ config('mail.brand_name') }}
</x-mail::message>
