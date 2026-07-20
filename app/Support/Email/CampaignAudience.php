<?php

declare(strict_types=1);

namespace App\Support\Email;

/**
 * جماهير الحملة القابلة للاختيار. المصدر الوحيد لمفاتيح الجمهور وتسمياتها، يستعمله
 * كلٌّ من فورم الإرسال (CheckboxList) و CampaignDispatcher عند حلّ العناوين.
 */
final class CampaignAudience
{
    public const ALL_CUSTOMERS = 'all_customers';

    public const VERIFIED_CUSTOMERS = 'verified_customers';

    public const PANEL_USERS = 'panel_users';

    public const EXTERNAL = 'external';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ALL_CUSTOMERS => 'كل العملاء المسجّلين',
            self::VERIFIED_CUSTOMERS => 'العملاء المُوثّقون فقط (بريد مؤكَّد)',
            self::PANEL_USERS => 'فريق العمل (مستخدمو اللوحة)',
            self::EXTERNAL => 'قائمة بريد خارجية (غير مسجّلين)',
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }
}
