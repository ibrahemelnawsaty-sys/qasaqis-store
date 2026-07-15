<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTracking extends Model
{
    protected $table = 'order_tracking';

    protected $fillable = [
        'order_id',
        'fbp',
        'fbc',
        'ga_client_id',
        'ga_session_id',
        'user_agent',
        'event_source_url',
        'ads_consent',
        'purchase_event_id',
        'meta_sent_at',
        'ga4_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ads_consent' => 'boolean',
            'meta_sent_at' => 'datetime',
            'ga4_sent_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
