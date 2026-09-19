<x-mail::message>
# {{ $newsletter->subject }}

{!! nl2br(e($newsletter->body)) !!}

Thanks,<br>
{{ config('mail.brand_name') }}
</x-mail::message>
