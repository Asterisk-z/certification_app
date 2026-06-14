<?php

namespace App\Services;

use App\Mail\RecipientInviteMail;
use App\Models\MailLog;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RecipientInviteService
{
    /**
     * Queue a portal invite email for a recipient and log it.
     */
    public function send(Recipient $recipient, ?User $causer = null): void
    {
        // Sign the API accept-endpoint, then rewrite the path into the SPA
        // route. The SPA reconstructs the API URL with the same query string,
        // so the signature still validates.
        $signed = URL::temporarySignedRoute('invite.show', now()->addDays(7), ['recipient' => $recipient->uuid]);
        $inviteUrl = str_replace('/api/auth/invite/', '/invite/', $signed);

        Mail::to($recipient->email)->queue(new RecipientInviteMail($recipient, $inviteUrl));

        MailLog::create([
            'mailable_type' => RecipientInviteMail::class,
            'recipient_email' => $recipient->email,
            'subject' => 'Access your credentials on '.config('app.name'),
            'status' => 'queued',
        ]);

        $recipient->user?->forceFill(['invited_at' => now()])->save();

        activity()->performedOn($recipient)->causedBy($causer)->log('recipient_invited');
    }
}
