<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TemplateBlock extends Model
{
    use HasFactory, HasUuid, LogsActivity;

    protected $fillable = [
        'certificate_template_id',
        'name',
        'slug',
        'type',
        'value',
        'is_dynamic',
        'is_visible',
        'is_default',
        'pos_x',
        'pos_y',
        'width',
        'height',
        'font_family',
        'font_size',
        'font_color',
        'font_weight',
        'text_align',
    ];

    protected function casts(): array
    {
        return [
            'type' => BlockType::class,
            'is_dynamic' => 'boolean',
            'is_visible' => 'boolean',
            'is_default' => 'boolean',
            'pos_x' => 'float',
            'pos_y' => 'float',
            'width' => 'float',
            'height' => 'float',
            'font_size' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class, 'certificate_template_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'value', 'is_visible'])->logOnlyDirty();
    }
}
