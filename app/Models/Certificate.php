<?php

namespace App\Models;

use App\Enums\CertificateStatus;
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
    use HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'certificate_template_id',
        'recipient_id',
        'group_id',
        'certificate_number',
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

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($term)).'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('certificate_number', 'like', $like)
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
