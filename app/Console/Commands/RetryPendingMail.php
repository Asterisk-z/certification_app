<?php

namespace App\Console\Commands;

use App\Enums\CertificateStatus;
use App\Jobs\SendCertificateJob;
use App\Jobs\SendLoggedMailJob;
use App\Mail\CertificateIssuedMail;
use App\Models\MailLog;
use Illuminate\Console\Command;

class RetryPendingMail extends Command
{
    protected $signature = 'mail:retry-pending';

    protected $description = 'Re-dispatch outgoing mail that is still queued or has failed (runs every 5 minutes)';

    public function handle(): int
    {
        $dispatched = 0;
        $certificates = 0;

        // Mail that went through LoggedMailer carries its own payload: just send it again.
        MailLog::retryable()->whereNotNull('payload')->orderBy('id')
            ->each(function (MailLog $log) use (&$dispatched) {
                SendLoggedMailJob::dispatch($log->id);
                $dispatched++;
            });

        // Certificate issue mails are rebuilt by SendCertificateJob (PDF/PNG +
        // CC list), so re-queue the certificate exactly like the admin Retry
        // button does. Only certificates that actually ended up Failed qualify;
        // anything else is still in flight on the queue.
        MailLog::retryable()->whereNull('payload')
            ->where('mailable_type', CertificateIssuedMail::class)
            ->whereNotNull('certificate_id')
            ->with('certificate')
            ->orderBy('id')
            ->each(function (MailLog $log) use (&$certificates) {
                $certificate = $log->certificate;

                if (! $certificate || $certificate->status !== CertificateStatus::Failed) {
                    return;
                }

                $certificate->forceFill(['status' => CertificateStatus::Queued])->save();
                SendCertificateJob::dispatch($certificate->id);
                $certificates++;
            });

        $this->info("Re-dispatched {$dispatched} mail(s) and re-queued {$certificates} certificate(s).");

        return self::SUCCESS;
    }
}
