<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * روابط السوشيال الرسمية للعلامة (قدّمها المالك): تُغذّي Organization.sameAs في
 * JSON-LD (بناء كيان العلامة لدى Google — يربط «قصاقيص» بالموقع) وأيقونات الفوتر.
 * فيسبوك = الصفحة الرسمية لا المجموعة (الصفحة كيانُ العلامة؛ المجموعة مجتمع يُذكر
 * في «من نحن»). updateOrCreate عبر النموذج فيُبطَل كاش الإعدادات تلقائيًّا.
 */
return new class extends Migration
{
    private const SOCIALS = [
        'social_facebook' => 'https://www.facebook.com/p/%D9%82%D8%B5%D8%A7%D9%82%D9%8A%D8%B5-%D8%A3%D8%B7%D9%81%D8%A7%D9%84-100076208663362/',
        'social_instagram' => 'https://www.instagram.com/qsaqis_kids/',
        'social_tiktok' => 'https://www.tiktok.com/@user6932917567139',
    ];

    public function up(): void
    {
        foreach (self::SOCIALS as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => 'social',
                    'value' => $value,
                    'type' => 'string',
                    'is_encrypted' => false,
                    'autoload' => true,
                ],
            );
        }
    }

    public function down(): void
    {
        // إعادة الحالة السابقة: فيسبوك للمجموعة، وتيك توك فارغًا (إنستغرام دون تغيير).
        Setting::where('key', 'social_facebook')
            ->update(['value' => 'https://www.facebook.com/groups/1596310100703409/']);
        Setting::where('key', 'social_tiktok')->update(['value' => '']);
    }
};
