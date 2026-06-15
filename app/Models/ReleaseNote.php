<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ReleaseNote extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'version',
        'title',
        'body',
        'released_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'released_on' => 'date',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Newest first — by release date, then most recently added. */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('released_on')->orderByDesc('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['version', 'title'])->logOnlyDirty();
    }
}
