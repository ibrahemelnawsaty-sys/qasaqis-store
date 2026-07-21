<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

/**
 * يزرع أقسام كتب الرئيسية الثلاثة المطابقة للحالة السابقة (مختارات/الأكثر مبيعًا/
 * وصل حديثًا) فتبدو الرئيسية مطابقة يوم أول، ثم يخصّصها الأدمن. النصوص حرفية عربية
 * (لا __() — الزرع يعمل في الطرفية حيث قد تكون اللغة en). firstOrCreate يحفظ تعديلات
 * الأدمن عند إعادة الزرع (لا يكتب فوقها).
 */
class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'title' => 'قصص اختارتها الأمهات',
                'eyebrow' => '⭐ الأكثر حبًا',
                'subtitle' => 'نخبة من أجمل كتبنا — كل واحدة بتحكي حكاية وبتزرع قيمة',
                'source_type' => 'featured',
                'item_limit' => 8,
                'sort_order' => 1,
                'cta_label' => 'شوفي كل الكتب ←',
                'cta_url' => url('/books'),
            ],
            [
                'title' => 'الأكثر مبيعًا',
                'eyebrow' => '🔥 الأكثر طلبًا',
                'subtitle' => 'الكتب اللي الأمهات طلبوها أكتر من غيرها — اختيارات مضمونة',
                'source_type' => 'bestsellers',
                'item_limit' => 8,
                'sort_order' => 2,
                'cta_label' => 'شوفي كل الكتب ←',
                'cta_url' => url('/books'),
            ],
            [
                'title' => 'أحدث ما أضفناه',
                'eyebrow' => '🆕 وصل حديثًا',
                'subtitle' => 'كتب جديدة تنضم لمكتبتنا باستمرار',
                'source_type' => 'latest',
                'item_limit' => 8,
                'sort_order' => 3,
                'cta_label' => null,
                'cta_url' => null,
            ],
        ];

        foreach ($sections as $section) {
            HomepageSection::firstOrCreate(
                ['title' => $section['title']],
                $section + ['is_active' => true],
            );
        }
    }
}
