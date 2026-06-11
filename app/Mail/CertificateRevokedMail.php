<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateRevokedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Certificate $certificate, public readonly ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Credential '.$this->certificate->certificate_number.' has been revoked',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.certificate-revoked',
            with: [
                'certificate' => $this->certificate,
                'reason' => $this->reason,
            ],
        );
    }
}
