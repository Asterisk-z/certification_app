<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CertificateIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Certificate $certificate) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your certificate '.$this->certificate->certificate_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.certificate-issued',
            with: [
                'certificate' => $this->certificate,
                'verifyUrl' => url('/?number='.urlencode($this->certificate->certificate_number)),
                'viewUrl' => url('/c/'.$this->certificate->uuid),
            ],
        );
    }

    public function attachments(): array
    {
        $path = $this->certificate->uploaded_file_path ?: $this->certificate->pdf_path;

        if (! $path || ! Storage::disk('local')->exists($path)) {
            return [];
        }

        return [
            Attachment::fromStorageDisk('local', $path)
                ->as($this->certificate->certificate_number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
