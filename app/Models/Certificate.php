<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Certificate extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'certificate_template_id',
        'recipient_id',
        'group_id',
        'certificate_number',
        'title',
        'data',
        'completion_date',
        'issue_date',
        'expiry_date',
        'status',
        'sent_at',
        'first_seen_at',
        'revoked_at',
        'renewed_from_id',
        'pdf_path',
        'png_path',
        'uploaded_file_path',
        'is_manual',
        'send_error',
    ];

    protected static function booted(): void
    {
        // A certificate must carry its issuing organization: the CC list, the
        // verification page's issuer and the org usage limits all key off it.
        // The tenancy trait only fills it from an authenticated org user, which
        // misses admin-run imports and anything created without a request, so
        // fall back to the template that owns it and then to the recipient.
        static::creating(function (self $certificate) {
            if ($certificate->organization_id) {
                return;
            }

            $certificate->organization_id = CertificateTemplate::withoutGlobalScopes()
                ->whereKey($certificate->certificate_template_id)
                ->value('organization_id')
                ?? Recipient::withoutGlobalScopes()
                    ->whereKey($certificate->recipient_id)
                    ->value('organization_id');
        });
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'status' => CertificateStatus::class,
            'completion_date' => 'date',
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'sent_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'is_manual' => 'boolean',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(Recipient::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function transitionTo(CertificateStatus $target): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            return false;
        }

        $this->status = $target;

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    /**
     * What this certificate certifies — the explicit title for offline
     * imports, otherwise the template name.
     */
    public function displayName(): ?string
    {
        return $this->title ?? $this->template?->name;
    }

    /**
     * The issuing organization's name exactly as it was entered on the system,
     * or null for platform/admin-issued credentials (organization_id is null),
     * which callers attribute to the platform itself.
     *
     * Organizations soft-delete, so this deliberately looks through the trash:
     * a credential must keep naming its real issuer after that organization
     * leaves, rather than silently reattributing itself to the platform.
     */
    public function issuerName(): ?string
    {
        if (! $this->organization_id) {
            return null;
        }

        $organization = $this->relationLoaded('organization')
            ? $this->getRelation('organization')
            : $this->organization()->withTrashed()->first();

        return $organization?->name;
    }

    /**
     * Certificates that count toward an organization's usage limits: the live
     * ones a holder/group/org currently has. Superseded states (renewed,
     * revoked, cancelled, expired, failed) are excluded, so e.g. renewing a
     * credential is net-zero against a per-recipient cap.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CertificateStatus::Pending,
            CertificateStatus::Queued,
            CertificateStatus::Sent,
        ]);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('certificate_number', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhereHas('recipient', function (Builder $r) use ($like) {
                    $r->where('full_name', 'like', $like)->orWhere('email', 'like', $like);
                })
                ->orWhereHas('template', function (Builder $t) use ($like) {
                    $t->where('name', 'like', $like)->orWhere('code', 'like', $like);
                });
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'certificate_number', 'issue_date', 'expiry_date'])
            ->logOnlyDirty();
    }
}
