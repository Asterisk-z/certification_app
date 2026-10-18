<?php

namespace App\Services;

use App\Jobs\SendLoggedMailJob;
use App\Models\MailLog;
use Illuminate\Mail\Mailable;

/**
 * The one way outgoing mail leaves the platform (apart from certificate
 * issue mails, which SendCertificateJob handles because they render files
 * first). It records the mail *and* its payload before anything is sent, so
 * /admin/logs/mail is the source of truth and `mail:retry-pending` can
 * re-send any row that never made it out.
 */
class LoggedMailer
{
    /**
     * @param  array<int, string>  $cc
     */
    public function queue(Mailable $mailable, string $to, array $cc = [], ?int $certificateId = null): MailLog
    {
        $mailable->to($to);

        if ($cc) {
            $mailable->cc($cc);
        }

        $log = MailLog::create([
            'mailable_type' => $mailable::class,
            'certificate_id' => $certificateId,
            'recipient_email' => $to,
            'subject' => $this->subjectOf($mailable),
            'status' => MailLog::STATUS_QUEUED,
            'payload' => MailLog::encodePayload($mailable),
        ]);

        SendLoggedMailJob::dispatch($log->id);

        return $log;
    }

    private function subjectOf(Mailable $mailable): ?string
    {
        if (method_exists($mailable, 'envelope')) {
            return $mailable->envelope()->subject;
        }

        return $mailable->subject ?: null;
    }
}
