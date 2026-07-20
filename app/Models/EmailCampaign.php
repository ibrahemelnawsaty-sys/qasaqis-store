<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * حملة بريدية إدارية. المحتوى (body_html) يُخزَّن **معقَّمًا** عبر CampaignHtml قبل
 * الحفظ (الباب 4.3)، فما في قاعدة البيانات آمن للطباعة. العدّادات تُحدَّث ذرّيًّا من
 * الـJob لكل مستلم.
 *
 * @property int $id
 * @property string $subject
 * @property string|null $preheader
 * @property string $body_html
 * @property array $audiences
 * @property string $status
 */
class EmailCampaign extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'created_by',
        'subject',
        'preheader',
        'template_key',
        'body_html',
        'audiences',
        'external_emails',
        'status',
        'batch_id',
        'total_recipients',
        'sent_count',
        'failed_count',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audiences' => 'array',
            'external_emails' => 'array',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<EmailRecipient>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(EmailRecipient::class);
    }

    /**
     * @return BelongsTo<User, EmailCampaign>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
