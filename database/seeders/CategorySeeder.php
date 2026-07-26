<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The six fixed catalogue sections (constitution 0.3). All six MUST always exist —
 * including the currently-empty ones (روايات، كتب طفولة مبكرة) — and MUST NOT be
 * removed just because they hold no books yet. Slugs are explicit Latin/URL-safe
 * strings because Str::slug() strips pure-Arabic text to an empty string.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Order = display order. color_hex uses brand tokens (constitution 0.1).
        // description = وصف SEO للقسم (نصّ تعريفيّ مرئيّ + وصف ميتا). تُضبط فقط للأقسام
        // الفارغة الوصف كي لا تدهس تعديل المالك من اللوحة. مِهجرة seed_category_descriptions
        // تُطبّقها على المواقع القائمة.
        $categories = [
            ['name' => 'روايات',            'slug' => 'novels',            'color_hex' => '#5B2A86', 'description' => 'روايات أطفال ويافعين مختارة بحبّ: قصص طويلة تنمّي الخيال وحبّ القراءة وتناسب القارئ الصغير المتقدّم. تصفّح مجموعة «قصاقيص أطفال» واختر ما يناسب عمر طفلك.'],
            ['name' => 'كتب علمية',          'slug' => 'science',           'color_hex' => '#F27405', 'description' => 'كتب علمية للأطفال تبسّط العلوم والطبيعة والفضاء بأسلوب ممتع ورسوم جذّابة تغذّي فضول طفلك وحبّه للاكتشاف. مجموعة مختارة بعناية من «قصاقيص أطفال».'],
            ['name' => 'سلوكيات ومشاعر',     'slug' => 'behavior-emotions', 'color_hex' => '#D6336C', 'description' => 'كتب السلوكيات والمشاعر للأطفال: تعلّم طفلك إدارة الغضب والخوف والمشاركة والثقة بالنفس عبر قصص محبّبة لا وعظًا مباشرًا. اختيار «قصاقيص أطفال».'],
            ['name' => 'قصص',               'slug' => 'stories',           'color_hex' => '#F2B705', 'description' => 'قصص أطفال مصوّرة مختارة بحبّ: حكايات قصيرة تزرع القيم وتنمّي الخيال واللغة لكل الأعمار. تصفّح مجموعة «قصاقيص أطفال» واقرأ لطفلك كل يوم.'],
            ['name' => 'كتب طفولة مبكرة',    'slug' => 'early-childhood',   'color_hex' => '#5B2A86', 'description' => 'كتب الطفولة المبكرة (٠–٥ سنوات): كتب أولى متينة بصور كبيرة وكلمات بسيطة تبني لغة طفلك وحبّه للكتاب مبكرًا. مجموعة «قصاقيص أطفال» المختارة.'],
            ['name' => 'كتب دينية',          'slug' => 'religious',         'color_hex' => '#F27405', 'description' => 'كتب دينية للأطفال بأسلوب محبّب: تعرّف طفلك على القيم والسيرة والأخلاق الإسلامية بلغة بسيطة ورسوم جميلة. اختيار «قصاقيص أطفال» بعناية.'],
        ];

        foreach ($categories as $index => $category) {
            // Idempotent: never truncates, safe to re-run (constitution 3.3).
            $model = Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'parent_id' => null,
                    'color_hex' => $category['color_hex'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            // الوصف يُضبط فقط إن كان فارغًا (لا يدهس تعديل المالك عند إعادة البذر).
            if (blank($model->description)) {
                $model->update(['description' => $category['description']]);
            }
        }
    }
}
