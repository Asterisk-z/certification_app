<?php

namespace App\Jobs;

use App\Models\MailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the mailable stored on a mail_logs row and records the outcome on
 * that same row. Deliberately single-try: failures are kept on the row and
 * the `mail:retry-pending` schedule re-dispatches them every five minutes
 * until the attempt cap, so nothing ends up in failed_jobs.
 */
class SendLoggedMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $mailLogId)
    {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $log = MailLog::find($this->mailLogId);

        if (! $log || ! $log->payload || ! $log->claimForSending()) {
            return;
        }

        try {
            Mail::send($log->mailable());
            $log->markSent();
        } catch (Throwable $e) {
            $log->markFailed($e);
            report($e);
        }
    }
}
