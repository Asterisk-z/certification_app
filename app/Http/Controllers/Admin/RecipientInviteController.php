<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RecipientInviteMail;
use App\Models\MailLog;
use App\Models\Recipient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class RecipientInviteController extends Controller
{
    public function store(Request $request, Recipient $recipient): JsonResponse
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
            'subject' => 'Access your certificates on '.config('app.name'),
            'status' => 'queued',
        ]);

        $recipient->user?->forceFill(['invited_at' => now()])->save();

        activity()->performedOn($recipient)->causedBy($request->user())->log('recipient_invited');

        return response()->json(['message' => "Invite sent to {$recipient->email}."]);
    }
}
