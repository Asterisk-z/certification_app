<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasUuid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organization_id',
        'invited_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function recipient(): HasOne
    {
        return $this->hasOne(Recipient::class);
    }

    /**
     * Ids of every recipient record that is this person. Each organization
     * keeps its own record per email and only the one whose invite was
     * accepted is linked through user_id, so the rest are matched on the
     * account's email (a recipient account only exists once its emailed
     * invite link has been followed, which proves the address).
     *
     * @return Collection<int, int>
     */
    public function recipientIds(): Collection
    {
        return Recipient::query()
            ->where(fn ($query) => $query
                ->where('user_id', $this->id)
                ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($this->email)]))
            ->pluck('id');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(CertificateTemplate::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isOrganization(): bool
    {
        return $this->role === UserRole::Organization;
    }
}
