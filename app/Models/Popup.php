<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Popup extends Model
{
    protected $fillable = [
        'title',
        'type',
        'content',
        'image_path',
        'survey_id',
        'cta_label',
        'cta_url',
        'display_trigger',
        'delay_seconds',
        'display_frequency',
        'target_pages',
        'target_devices',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_pages' => 'array',
            'target_devices' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
