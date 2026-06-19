<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Organization extends Model
{
    use HasFactory, HasUuid, LogsActivity, SoftDeletes;

    /**
     * Features an org may use (on/off gating). Absent/true = allowed; false = blocked.
     */
    public const FEATURES = ['templates', 'groups', 'recipients', 'certificates'];

    /**
     * Per-organization usage caps. A positive integer caps the count; a
     * missing/null entry means unlimited. Enforced for organization users only
     * (admins bypass) via OrganizationLimitService.
     */
    public const LIMITS = [
        'certificates',
        'certificate_admins',
        'templates',
        'groups',
        'recipients',
        'certificates_per_recipient',
        'groups_per_recipient',
        'certificates_per_group',
    ];

    protected $fillable = [
        'name',
        'description',
        'email',
        'logo_path',
        'code',
        'status',
        'features',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Whether the org is allowed to use a feature. Missing entry = allowed.
     */
    public function allows(string $feature): bool
    {
        $value = $this->features[$feature] ?? true;

        return $value !== false && $value !== 0;
    }

    /**
     * The configured cap for a limit key (see self::LIMITS), or null when
     * unlimited. Only positive integers act as a cap.
     */
    public function limitFor(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return is_int($value) && $value > 0 ? $value : null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(Recipient::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'code', 'status', 'features', 'limits'])
            ->logOnlyDirty();
    }
}
