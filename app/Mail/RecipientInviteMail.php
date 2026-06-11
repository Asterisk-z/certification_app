<?php

namespace App\Mail;

use App\Models\Recipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecipientInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Recipient $recipient,
        public readonly string $inviteUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Access your certificates on '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.recipient-invite',
            with: [
                'recipient' => $this->recipient,
                'inviteUrl' => $this->inviteUrl,
            ],
        );
    }
}
