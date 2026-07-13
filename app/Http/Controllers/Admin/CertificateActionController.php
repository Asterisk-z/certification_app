<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendCertificateJob;
use App\Mail\CertificateRevokedMail;
use App\Models\Certificate;
use App\Models\CertificateCcEmail;
use App\Models\MailLog;
use App\Notifications\CertificateRevokedNotification;
use App\Services\CertificateIssueService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class CertificateActionController extends Controller
{
    public function __construct(private readonly CertificateIssueService $issuer) {}

    public function revoke(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->with('recipient')->firstOrFail();
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        if (! $certificate->transitionTo(CertificateStatus::Revoked)) {
            return response()->json(['message' => "A {$certificate->status->value} credential cannot be revoked."], 422);
        }

        $certificate->revoked_at = now();
        $certificate->save();

        $this->notifyRevocation($certificate, $reason);

        activity()->performedOn($certificate)->causedBy($request->user())
            ->withProperties(['reason' => $reason])->log('certificate_revoked');

        return response()->json($certificate->fresh()->load('recipient', 'template'));
    }

    /**
     * Restore a revoked certificate back to "sent".
     */
    public function unrevoke(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        if ($certificate->status !== CertificateStatus::Revoked) {
            return response()->json(['message' => 'Only revoked credentials can be restored.'], 422);
        }

        // A revoked certificate returns to "sent" (or "expired" if past expiry).
        $certificate->status = $certificate->isExpired() ? CertificateStatus::Expired : CertificateStatus::Sent;
        $certificate->revoked_at = null;
        $certificate->save();

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_unrevoked');

        return response()->json($certificate->fresh()->load('recipient', 'template'));
    }

    public function resend(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        if (! in_array($certificate->status, [CertificateStatus::Sent, CertificateStatus::Failed, CertificateStatus::Pending, CertificateStatus::Queued], true)) {
            return response()->json(['message' => "A {$certificate->status->value} credential cannot be resent."], 422);
        }

        // Re-queue and (re)dispatch the send job. Queued credentials are
        // already in this state — re-dispatching retries a job that never
        // ran (e.g. the worker was down when it was first queued).
        $certificate->status = CertificateStatus::Queued;
        $certificate->save();
        SendCertificateJob::dispatch($certificate->id);

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_resent');

        return response()->json($certificate->fresh()->load('recipient', 'template'));
    }

    public function renew(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();

        if (! in_array($certificate->status, [CertificateStatus::Sent, CertificateStatus::Expired], true)) {
            return response()->json(['message' => 'Only sent or expired credentials can be renewed.'], 422);
        }

        // Renewal re-renders the document from the template, so a credential
        // without one (e.g. an offline import) can't be renewed.
        if (! $certificate->certificate_template_id) {
            return response()->json(['message' => 'This credential has no template, so it cannot be renewed and regenerated.'], 422);
        }

        $validated = $request->validate([
            'issue_date' => ['nullable', 'date'],
            'completion_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:issue_date'],
        ]);

        $issueDate = isset($validated['issue_date']) ? Carbon::parse($validated['issue_date']) : now();
        $completionDate = isset($validated['completion_date'])
            ? Carbon::parse($validated['completion_date'])
            : $certificate->completion_date;

        if ($completionDate && $completionDate->gt($issueDate)) {
            return response()->json(['message' => 'The completion date cannot be after the issue date.'], 422);
        }

        $new = $this->issuer->renew(
            $certificate,
            $issueDate,
            isset($validated['expiry_date']) ? Carbon::parse($validated['expiry_date']) : null,
            $completionDate,
        );

        activity()->performedOn($certificate)->causedBy($request->user())
            ->withProperties(['new_certificate' => $new->certificate_number])->log('certificate_renewed');

        return response()->json($new->load('recipient', 'template'), 201);
    }

    /**
     * Restore a soft-deleted certificate.
     */
    public function restore(Request $request, string $uuid): JsonResponse
    {
        $certificate = Certificate::onlyTrashed()->where('uuid', $uuid)->firstOrFail();
        $certificate->restore();

        activity()->performedOn($certificate)->causedBy($request->user())->log('certificate_restored');

        return response()->json($certificate->fresh()->load('recipient', 'template'));
    }

    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['revoke', 'cancel', 'delete', 'restore', 'resend'])],
            'uuids' => ['required', 'array', 'min:1', 'max:500'],
            'uuids.*' => ['uuid'],
        ]);

        $action = $validated['action'];
        $query = $action === 'restore'
            ? Certificate::onlyTrashed()->whereIn('uuid', $validated['uuids'])
            : Certificate::whereIn('uuid', $validated['uuids']);

        $affected = 0;

        foreach ($query->with('recipient')->get() as $certificate) {
            $affected += (int) match ($action) {
                'revoke' => $this->bulkRevoke($certificate),
                'cancel' => $this->bulkCancel($certificate),
                'delete' => (bool) $certificate->delete(),
                'restore' => (bool) $certificate->restore(),
                'resend' => $this->bulkResend($certificate),
            };
        }

        activity()->causedBy($request->user())
            ->withProperties(['action' => $action, 'requested' => count($validated['uuids']), 'affected' => $affected])
            ->log('certificates_bulk_'.$action);

        return response()->json(['message' => "{$affected} credential(s) {$action}d.", 'affected' => $affected]);
    }

    private function bulkRevoke(Certificate $certificate): bool
    {
        if (! $certificate->transitionTo(CertificateStatus::Revoked)) {
            return false;
        }

        $certificate->revoked_at = now();
        $certificate->save();
        $this->notifyRevocation($certificate, null);

        return true;
    }

    private function bulkCancel(Certificate $certificate): bool
    {
        if (! $certificate->transitionTo(CertificateStatus::Cancelled)) {
            return false;
        }

        $certificate->save();

        return true;
    }

    private function bulkResend(Certificate $certificate): bool
    {
        if (! in_array($certificate->status, [CertificateStatus::Sent, CertificateStatus::Failed, CertificateStatus::Pending, CertificateStatus::Queued], true)) {
            return false;
        }

        $certificate->status = CertificateStatus::Queued;
        $certificate->save();
        SendCertificateJob::dispatch($certificate->id);

        return true;
    }

    private function notifyRevocation(Certificate $certificate, ?string $reason): void
    {
        $mailer = Mail::to($certificate->recipient->email);

        if ($cc = CertificateCcEmail::recipientsFor($certificate)) {
            $mailer->cc($cc);
        }

        $mailer->queue(new CertificateRevokedMail($certificate, $reason));

        $certificate->recipient->user?->notify(new CertificateRevokedNotification($certificate));

        MailLog::create([
            'mailable_type' => CertificateRevokedMail::class,
            'certificate_id' => $certificate->id,
            'recipient_email' => $certificate->recipient->email,
            'subject' => 'Credential '.$certificate->certificate_number.' has been revoked',
            'status' => 'queued',
        ]);
    }
}
