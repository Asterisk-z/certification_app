<x-mail::message>
# {{ $newsletter->subject }}

{!! nl2br(e($newsletter->body)) !!}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
