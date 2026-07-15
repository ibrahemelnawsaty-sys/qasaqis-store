<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Baseline CMS pages (constitution 0.8): about, shipping, returns, FAQ, privacy.
 * These give the admin real, editable content in the Pages resource instead of an
 * empty CMS. Content is stored as HTML (RichEditor) and is intentionally generic
 * starter copy — the admin refines it from the panel.
 *
 * Idempotent: updateOrCreate keyed on the unique `slug`. Re-running refreshes the
 * seeded baseline without duplicating rows; admin edits to slugs are preserved
 * (a re-seed only touches these exact slugs). Slugs are English + hyphens per the
 * Pages resource convention. All pages ship published so they resolve immediately.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $pages = [
            [
                'slug' => 'about',
                'title' => 'من نحن',
                'sort_order' => 1,
                'content' => '<p>«قصاقيص أطفال» مكتبة إلكترونية متخصّصة في كتب الأطفال المنسّقة بعناية،'
                    .' نختار لكم أجمل الحكايات التي تزرع القيم وتنمّي المهارات وتُشعل خيال الصغار.</p>'
                    .'<p>نؤمن أن الكتاب الجيد رفيقٌ يكبر مع الطفل، لذلك نحرص على انتقاء إصدارات من دور نشر'
                    .' موثوقة، وتقديمها لكم بأسعار مناسبة وخدمة توصيل سهلة.</p>',
            ],
            [
                'slug' => 'shipping-policy',
                'title' => 'سياسة الشحن',
                'sort_order' => 2,
                'content' => '<p>نقوم بتجهيز طلبك وشحنه في أقرب وقت بعد تأكيد الطلب.</p>'
                    .'<ul>'
                    .'<li>يبدأ تجهيز الطلب بعد تأكيد الدفع أو اختيار الدفع عند الاستلام.</li>'
                    .'<li>تختلف مدّة التوصيل ورسومه حسب المنطقة، وتظهر التفاصيل عند إتمام الطلب.</li>'
                    .'<li>يصلك إشعار بحالة الطلب، ويمكنك متابعتنا عبر واتساب لأي استفسار.</li>'
                    .'</ul>'
                    .'<p>هذا النص مبدئي، ويحرّره فريق المتجر من لوحة التحكم.</p>',
            ],
            [
                'slug' => 'returns-policy',
                'title' => 'الاستبدال والاسترجاع',
                'sort_order' => 3,
                'content' => '<p>رضاكم يهمّنا. إذا وصلك المنتج تالفًا أو مختلفًا عمّا طلبت، يمكنك طلب الاستبدال أو الاسترجاع.</p>'
                    .'<ul>'
                    .'<li>تواصل معنا خلال مدّة معقولة من استلام الطلب مع صورة توضّح المشكلة.</li>'
                    .'<li>يجب أن يكون الكتاب بحالته الأصلية دون تلف ناتج عن الاستخدام.</li>'
                    .'<li>يتم الاستبدال أو ردّ المبلغ بعد مراجعة الطلب وفق الحالة.</li>'
                    .'</ul>'
                    .'<p>هذا النص مبدئي، ويحرّره فريق المتجر من لوحة التحكم.</p>',
            ],
            [
                'slug' => 'faq',
                'title' => 'الأسئلة الشائعة',
                'sort_order' => 4,
                'content' => '<h3>كيف أطلب كتابًا؟</h3>'
                    .'<p>اختَر الكتاب، أضِفه إلى السلة، ثم أكمل بيانات الطلب واختر طريقة الدفع المناسبة.</p>'
                    .'<h3>ما طرق الدفع المتاحة؟</h3>'
                    .'<p>يمكنك الدفع يدويًا (إنستاباي/فودافون كاش) أو الدفع عند الاستلام، إضافةً إلى الدفع الأونلاين عند تفعيله.</p>'
                    .'<h3>كم تستغرق مدّة التوصيل؟</h3>'
                    .'<p>تختلف حسب منطقتك، وتظهر التفاصيل عند إتمام الطلب.</p>',
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'سياسة الخصوصية',
                'sort_order' => 5,
                'content' => '<p>نحترم خصوصيتك ونحمي بياناتك.</p>'
                    .'<ul>'
                    .'<li>نجمع فقط البيانات اللازمة لتنفيذ طلبك والتواصل معك بشأنه.</li>'
                    .'<li>لا نشارك بياناتك مع أطراف خارجية إلا بالقدر اللازم لإتمام الخدمة (كالشحن).</li>'
                    .'<li>يمكنك التواصل معنا في أي وقت بخصوص بياناتك.</li>'
                    .'</ul>'
                    .'<p>هذا النص مبدئي، ويحرّره فريق المتجر من لوحة التحكم.</p>',
            ],
        ];

        foreach ($pages as $page) {
            Page::updateOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'content' => $page['content'],
                    'template' => null,
                    'is_published' => true,
                    'published_at' => $now,
                    'sort_order' => $page['sort_order'],
                ],
            );
        }
    }
}
