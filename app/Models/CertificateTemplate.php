<?php

namespace App\Models;

use App\Enums\DurationType;
use App\Enums\TemplateStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CertificateTemplate extends Model
{
    use BelongsToOrganization, HasFactory, HasUuid, LogsActivity, SoftDeletes;

    /**
     * Default dynamic blocks seeded onto every new template. Slugs here are
     * always present as columns in the Excel import format.
     */
    public const DEFAULT_BLOCK_FONT_SIZE = 60;

    public const DEFAULT_BLOCK_WIDTH = 300;

    public const DEFAULT_BLOCKS = [
        ['name' => 'Full Name', 'slug' => 'full_name', 'type' => 'text', 'font_size' => self::DEFAULT_BLOCK_FONT_SIZE, 'pos_y' => 200],
        ['name' => 'Completion Date', 'slug' => 'completion_date', 'type' => 'text', 'font_size' => self::DEFAULT_BLOCK_FONT_SIZE, 'pos_y' => 300],
        ['name' => 'Issue Date', 'slug' => 'issue_date', 'type' => 'text', 'font_size' => self::DEFAULT_BLOCK_FONT_SIZE, 'pos_y' => 400],
        ['name' => 'Credential Number', 'slug' => 'certificate_number', 'type' => 'text', 'font_size' => self::DEFAULT_BLOCK_FONT_SIZE, 'pos_y' => 500],
        ['name' => 'QR Code', 'slug' => 'qr_code', 'type' => 'qrcode', 'font_size' => self::DEFAULT_BLOCK_FONT_SIZE, 'pos_y' => 600],
    ];

    public const RESERVED_SLUGS = ['full_name', 'email', 'completion_date', 'issue_date', 'certificate_number', 'qr_code'];

    protected $fillable = [
        'organization_id',
        'user_id',
        'name',
        'code',
        'background_image',
        'bg_width',
        'bg_height',
        'duration',
        'duration_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => TemplateStatus::class,
            'duration_type' => DurationType::class,
            'bg_width' => 'integer',
            'bg_height' => 'integer',
            'duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TemplateBlock::class)->orderBy('id');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function dynamicBlocks(): HasMany
    {
        return $this->blocks()->where('is_dynamic', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'duration', 'duration_type', 'status'])
            ->logOnlyDirty();
    }
}
