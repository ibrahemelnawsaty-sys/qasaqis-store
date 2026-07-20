<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مستلم واحد داخل حملة. `token` سرّ (48 حرفًا) هو مفتاح صفحة إلغاء الاشتراك؛
 * لذلك يُخفى من التسلسل الافتراضي حتى لا يتسرّب في استجابة/سجل.
 *
 * @property int $id
 * @property string $email
 * @property string|null $name
 * @property string $source
 * @property string $token
 * @property string $status
 */
class EmailRecipient extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'email_campaign_id',
        'email',
        'name',
        'source',
        'token',
        'status',
        'sent_at',
        'error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EmailCampaign, EmailRecipient>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}
