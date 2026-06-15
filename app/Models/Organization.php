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
     * Features an org may use. Absent/true = allowed; a numeric value caps the
     * record count for that feature.
     */
    public const FEATURES = ['templates', 'groups', 'recipients', 'certificates'];

    protected $fillable = [
        'name',
        'description',
        'email',
        'logo_path',
        'code',
        'status',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
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
     * The configured cap for a feature, or null when unlimited.
     */
    public function limitFor(string $feature): ?int
    {
        $value = $this->features[$feature] ?? null;

        return is_int($value) ? $value : null;
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
            ->logOnly(['name', 'email', 'code', 'status', 'features'])
            ->logOnlyDirty();
    }
}
