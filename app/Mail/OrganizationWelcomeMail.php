<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrganizationWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Organization $organization,
        public readonly string $loginUrl,
        // True when a separate password-setup link email is also on its way, so
        // the body points there instead of telling them to sign in directly.
        public readonly bool $setupLinkSent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization-welcome',
            with: [
                'organization' => $this->organization,
                'loginUrl' => $this->loginUrl,
                'setupLinkSent' => $this->setupLinkSent,
            ],
        );
    }
}
