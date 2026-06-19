<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Group extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsActivity, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
    ];

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Recipient::class)->withTimestamps();
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'description'])->logOnlyDirty();
    }
}
