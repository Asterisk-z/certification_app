<?php

namespace App\Services;

use App\Enums\CertificateStatus;
use App\Jobs\SendCertificateJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Group;
use App\Models\Recipient;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CertificateIssueService
{
    public function __construct(private readonly CertificateNumberService $numbers) {}

    /**
     * Create certificates for a set of recipients and queue them for sending.
     *
     * @param  Collection<int, Recipient>  $recipients
     * @return Collection<int, Certificate>
     */
    public function issue(
        CertificateTemplate $template,
        Collection $recipients,
        Carbon $completionDate,
        Carbon $issueDate,
        array $extraData = [],
        ?Group $group = null,
        bool $queueSend = true,
    ): Collection {
        return $recipients->map(function (Recipient $recipient) use ($template, $completionDate, $issueDate, $extraData, $group, $queueSend) {
            $certificate = $this->createCertificate($template, $recipient, $completionDate, $issueDate, $extraData, $group);

            if ($queueSend) {
                $this->queueSend($certificate);
            }

            return $certificate;
        });
    }

    public function createCertificate(
        CertificateTemplate $template,
        Recipient $recipient,
        Carbon $completionDate,
        Carbon $issueDate,
        array $extraData = [],
        ?Group $group = null,
        ?string $manualNumber = null,
    ): Certificate {
        return $template->certificates()->create([
            'recipient_id' => $recipient->id,
            'group_id' => $group?->id,
            'certificate_number' => $manualNumber ?: $this->numbers->next($template),
            'data' => $extraData,
            'completion_date' => $completionDate,
            'issue_date' => $issueDate,
            'expiry_date' => $this->expiryFor($template, $issueDate),
            'status' => CertificateStatus::Pending,
            'is_manual' => $manualNumber !== null,
        ]);
    }

    public function queueSend(Certificate $certificate): void
    {
        if ($certificate->transitionTo(CertificateStatus::Queued)) {
            $certificate->save();
            SendCertificateJob::dispatch($certificate->id);
        }
    }

    /**
     * Renew: mark the old certificate renewed and issue a fresh one with the
     * same data but new dates and number.
     */
    public function renew(Certificate $certificate, ?Carbon $issueDate = null): Certificate
    {
        $issueDate = $issueDate ?: now();
        $template = $certificate->template;

        $new = $template->certificates()->create([
            'recipient_id' => $certificate->recipient_id,
            'group_id' => $certificate->group_id,
            'certificate_number' => $this->numbers->next($template),
            'data' => $certificate->data,
            'completion_date' => $certificate->completion_date,
            'issue_date' => $issueDate,
            'expiry_date' => $this->expiryFor($template, $issueDate),
            'status' => CertificateStatus::Pending,
            'renewed_from_id' => $certificate->id,
        ]);

        $certificate->transitionTo(CertificateStatus::Renewed);
        $certificate->save();

        $this->queueSend($new);

        return $new;
    }

    public function expiryFor(CertificateTemplate $template, Carbon $issueDate): ?Carbon
    {
        if (! $template->duration || ! $template->duration_type) {
            return null;
        }

        return $template->duration_type->addTo($issueDate, $template->duration);
    }
}
