<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomepageBlock;
use Illuminate\Database\Seeder;

/**
 * Baseline homepage CMS blocks (constitution 0.8) so the HomepageBlocks resource
 * ships with real, editable examples instead of an empty screen — the admin sees
 * exactly how to add banners/sections. All blocks live in area=homepage and use
 * the flat content keys the storefront actually reads (body, url, image_url, cta)
 * as defined in partials/home/slider.blade.php and partials/home/block.blade.php.
 *
 * The slider ships WITHOUT an image_url: the slider partial renders a brand-color
 * gradient fallback when no image exists — no fabricated cover/banner image
 * (constitution 5.3 / 11.22). The admin uploads a real image and pastes its path.
 *
 * Idempotent: updateOrCreate keyed on the unique `key`.
 */
class HomepageBlockSeeder extends Seeder
{
    public function run(): void
    {
        $blocks = [
            [
                'key' => 'home_slider_1',
                'type' => 'slider',
                'title' => 'أهلًا بكِ في قصاقيص أطفال 🌟',
                'sort_order' => 1,
                'content' => [
                    'body' => 'مكتبة قصص أطفال مختارة بحب — قيم وسلوكيات ومغامرات تكبر مع طفلك.',
                    'url' => route('books.index'),
                    'cta' => 'تصفّحي المكتبة',
                    'image_url' => 'images/slider/slide-1.webp',
                ],
            ],
            [
                'key' => 'home_slider_2',
                'type' => 'slider',
                'title' => 'شحن دولي لكل الدول 🌍',
                'sort_order' => 2,
                'content' => [
                    'body' => 'نوصّل كتبك لأي دولة عربية بأمان — اطلبي وأنتِ مطمئنة.',
                    'url' => route('books.index'),
                    'cta' => 'اطلبي الآن',
                    'image_url' => 'images/slider/slide-2.webp',
                ],
            ],
            [
                'key' => 'home_slider_3',
                'type' => 'slider',
                'title' => 'عروض وخصومات على كتب مختارة 🎁',
                'sort_order' => 3,
                'content' => [
                    'body' => 'جودة عالية بأسعار في المتناول لكل بيت — لا تفوّتي العروض!',
                    'url' => route('books.offers'),
                    'cta' => 'شوفي العروض',
                    'image_url' => 'images/slider/slide-3.webp',
                ],
            ],
            [
                'key' => 'home_slider_4',
                'type' => 'slider',
                'title' => 'نصائح تربوية تهمّك 📖',
                'sort_order' => 4,
                'content' => [
                    'body' => 'مدوّنة قصاقيص: مقالات عن حب القراءة والمشاعر وتربية طفلك.',
                    'url' => route('blog.index'),
                    'cta' => 'زوري المدوّنة',
                    'image_url' => 'images/slider/slide-4.webp',
                ],
            ],
            [
                'key' => 'home_intro',
                'type' => 'text',
                'title' => 'لماذا قصاقيص أطفال؟',
                'sort_order' => 2,
                'content' => [
                    'body' => "نختار لكم إصدارات من دور نشر موثوقة، ونقدّمها بأسعار مناسبة وخدمة توصيل سهلة.\n"
                        .'كل كتاب رفيقٌ يكبر مع طفلك ويغرس فيه أجمل القيم والمهارات.',
                    'url' => '',
                    'cta' => '',
                ],
            ],
            [
                'key' => 'home_cta',
                'type' => 'cta',
                'title' => 'ابدأوا رحلة القراءة الآن',
                'sort_order' => 3,
                'content' => [
                    'body' => 'اكتشفوا أحدث العروض على مجموعة مختارة من كتب الأطفال.',
                    'url' => route('books.offers'),
                    'cta' => 'شاهدوا العروض',
                ],
            ],
        ];

        foreach ($blocks as $block) {
            HomepageBlock::updateOrCreate(
                ['key' => $block['key']],
                [
                    'area' => 'homepage',
                    'type' => $block['type'],
                    'title' => $block['title'],
                    'content' => $block['content'],
                    'is_active' => true,
                    'sort_order' => $block['sort_order'],
                ],
            );
        }
    }
}
