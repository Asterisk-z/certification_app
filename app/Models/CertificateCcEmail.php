<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An address that is BCC/CC'd on every certificate mail for an organization.
 * Managed via a small CRUD by the org's admin (and by the platform admin for
 * any org). See CertificateCcEmail::recipientsFor().
 */
class CertificateCcEmail extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'organization_id',
        'email',
        'name',
    ];

    /**
     * Resolve the list of CC addresses for a certificate's mail.
     *
     * Uses the org's configured CC list; when none is configured we fall back
     * to the organization's own contact address so the org admin is copied by
     * default, as required. The certificate's own recipient is never CC'd
     * (they are the To: address) and the result is de-duplicated.
     *
     * @return array<int, string>
     */
    public static function recipientsFor(Certificate $certificate): array
    {
        // Query by organization_id directly: this runs from the queue worker
        // (no auth user, so the tenant global scope is inert) as well as from
        // request context, and must resolve the same list either way.
        $emails = static::query()
            ->where('organization_id', $certificate->organization_id)
            ->pluck('email');

        if ($emails->isEmpty() && $certificate->organization_id) {
            $orgEmail = Organization::whereKey($certificate->organization_id)->value('email');

            if ($orgEmail) {
                $emails = collect([$orgEmail]);
            }
        }

        $recipientEmail = mb_strtolower(trim((string) $certificate->recipient?->email));

        return $emails
            ->map(fn ($email) => trim((string) $email))
            ->filter()
            ->unique(fn ($email) => mb_strtolower($email))
            ->reject(fn ($email) => mb_strtolower($email) === $recipientEmail)
            ->values()
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['email', 'name'])->logOnlyDirty();
    }
}
