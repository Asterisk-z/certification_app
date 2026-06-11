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
        $attachments = [];

        $pdf = $this->certificate->uploaded_file_path ?: $this->certificate->pdf_path;

        if ($pdf && Storage::disk('local')->exists($pdf)) {
            $attachments[] = Attachment::fromStorageDisk('local', $pdf)
                ->as($this->certificate->certificate_number.'.pdf')
                ->withMime('application/pdf');
        }

        // Image version for easy sharing/embedding (skipped for manual uploads).
        if (! $this->certificate->uploaded_file_path
            && $this->certificate->png_path
            && Storage::disk('local')->exists($this->certificate->png_path)) {
            $attachments[] = Attachment::fromStorageDisk('local', $this->certificate->png_path)
                ->as($this->certificate->certificate_number.'.png')
                ->withMime('image/png');
        }

        return $attachments;
    }
}
