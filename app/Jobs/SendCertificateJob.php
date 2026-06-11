<?php

namespace App\Jobs;

use App\Enums\CertificateStatus;
use App\Mail\CertificateIssuedMail;
use App\Models\Certificate;
use App\Models\MailLog;
use App\Notifications\CertificateIssuedNotification;
use App\Services\CertificateRenderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCertificateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $certificateId)
    {
        $this->onQueue('emails');
    }

    public function handle(CertificateRenderService $renderer): void
    {
        $certificate = Certificate::withTrashed()->with('recipient', 'template')->find($this->certificateId);

        if (! $certificate || $certificate->trashed() || $certificate->status !== CertificateStatus::Queued) {
            return;
        }

        // Generate the PDF + PNG unless an admin manually uploaded the file.
        if (! $certificate->uploaded_file_path) {
            $renderer->pdf($certificate);
            $renderer->png($certificate);
        }

        Mail::to($certificate->recipient->email)->send(new CertificateIssuedMail($certificate));

        $certificate->forceFill([
            'status' => CertificateStatus::Sent,
            'sent_at' => now(),
            'send_error' => null,
        ])->save();

        MailLog::create([
            'mailable_type' => CertificateIssuedMail::class,
            'certificate_id' => $certificate->id,
            'recipient_email' => $certificate->recipient->email,
            'subject' => 'Your certificate '.$certificate->certificate_number,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        activity()->performedOn($certificate)->log('certificate_sent');

        $certificate->recipient->user?->notify(new CertificateIssuedNotification($certificate));
    }

    public function failed(?Throwable $exception): void
    {
        $certificate = Certificate::with('recipient')->find($this->certificateId);

        if (! $certificate) {
            return;
        }

        $certificate->forceFill([
            'status' => CertificateStatus::Failed,
            'send_error' => mb_strimwidth($exception?->getMessage() ?? 'Unknown error', 0, 1000, '…'),
        ])->save();

        MailLog::create([
            'mailable_type' => CertificateIssuedMail::class,
            'certificate_id' => $certificate->id,
            'recipient_email' => $certificate->recipient->email,
            'subject' => 'Your certificate '.$certificate->certificate_number,
            'status' => 'failed',
            'error' => $exception?->getMessage(),
        ]);

        activity()->performedOn($certificate)->log('certificate_send_failed');
    }
}
