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
                // قصّة العلامة الحقيقية (بدأت 2022 كمجتمع فيسبوك؛ كتب + ألعاب + أوراق
                // عمل) لبناء الثقة والفهرسة. مِهجرة enrich_about_page_content تُبقيها
                // متطابقةً على المواقع القائمة. الوسوم مقصورة على ما يسمح به HtmlSanitizer.
                'content' => '<p><strong>«قصاقيص أطفال»</strong> رحلة بدأت عام <strong>2022</strong> من حبٍّ بسيط: أن تصير القراءة متعةً يوميّةً في كل بيت. انطلقنا مجتمعًا صغيرًا على فيسبوك يجمع أمهاتٍ وآباءً يؤمنون أن الكتاب الجيّد رفيقٌ يكبر مع الطفل — نتشارك أنشطتنا، ونشجّع العائلات على الاستمتاع مع أطفالهم في كل الأعمار، ونرحّب بأنشطتكم أنتم أيضًا.</p>'
                    .'<p>ومن هذا المجتمع وُلد متجرنا <strong>qasaqis.store</strong>: مكانٌ نختار فيه لكم بعناية <strong>كتب الأطفال</strong> و<strong>الألعاب الهادفة</strong> و<strong>أوراق العمل التعليمية والتفاعلية</strong> — كل ما يعين طفلك على التعلّم واللعب والنموّ بحبّ.</p>'
                    .'<h2>رسالتنا</h2>'
                    .'<p>نؤمن أن كل طفلٍ يستحق حكايةً تُشعل خياله وقيمةً تكبر معه. لذلك نبحث ونختار وننتقي بأنفسنا، حتى نضع بين يديك مجموعةً مختارةً بحبّ لا مجرّد تكديسٍ للعناوين: كتبًا تزرع القيم، وتنمّي المهارات، وتصنع من وقت القراءة لحظةً تنتظرها العائلة كلّها.</p>'
                    .'<h2>ماذا تجد في قصاقيص</h2>'
                    .'<ul>'
                    .'<li><strong>كتب أطفال لكل الأعمار:</strong> قصص وحكايات مصوّرة، وروايات، وكتب تعليمية وسلوكية ودينية.</li>'
                    .'<li><strong>ألعاب هادفة:</strong> تجمع بين المتعة والتعلّم وتنمية مهارات طفلك.</li>'
                    .'<li><strong>أوراق عمل تعليمية وتفاعلية:</strong> أنشطة تُثري وقت الطفل وتدعم تعلّمه في البيت.</li>'
                    .'</ul>'
                    .'<h2>لماذا تختار العائلات قصاقيص</h2>'
                    .'<ul>'
                    .'<li><strong>اختيارٌ بعناية:</strong> ننتقي كل عنوانٍ بأنفسنا من دور نشرٍ موثوقة، ولا نعرض كل ما يصدر.</li>'
                    .'<li><strong>مجتمعٌ حقيقيّ:</strong> بدأنا من مجموعةٍ من الأهل عام 2022، وما زلنا نتشارك الأنشطة والأفكار والحكايات.</li>'
                    .'<li><strong>قريبون منك:</strong> نخدم العائلات في مصر باختيارٍ مدروس وتواصلٍ سهل.</li>'
                    .'</ul>'
                    .'<h2>انضمّ إلى رحلتنا</h2>'
                    .'<p>تصفّح مجموعتنا المختارة من كتب الأطفال، وتابِع عروضنا المتجدّدة، واقرأ في مدوّنتنا نصائح اختيار الكتاب المناسب لعمر طفلك. ولأن قصاقيص بدأت مجتمعًا قبل أن تكون متجرًا، يسعدنا أن نراك بيننا نتشارك متعة القراءة مع أطفالنا.</p>',
            ],
            [
                'slug' => 'shipping-policy',
                'title' => 'سياسة الشحن',
                'sort_order' => 2,
                'content' => '<p>نشحن كتبنا إلى <strong>جميع الدول</strong> 🌍 — نجهّز طلبك ونشحنه في أقرب وقت بعد تأكيده.</p>'
                    .'<ul>'
                    .'<li>شحن دولي لكل الدول العربية وغيرها.</li>'
                    .'<li>تختلف مدّة التوصيل ورسوم الشحن حسب الدولة/المنطقة، وتظهر التفاصيل عند إتمام الطلب أو بالتواصل معنا عبر واتساب.</li>'
                    .'<li>يصلك إشعار بحالة الطلب في كل مرحلة.</li>'
                    .'</ul>',
            ],
            [
                'slug' => 'returns-policy',
                'title' => 'سياسة الاسترجاع',
                'sort_order' => 3,
                'content' => '<p>نظرًا لطبيعة الكتب، <strong>لا يوجد استبدال أو استرجاع</strong> بعد إتمام الشراء.</p>'
                    .'<p>ولأن رضاكم يهمّنا، نحرص على وصول كتبكم بحالة ممتازة ومغلّفة بعناية. في حال وصول كتاب تالف أو حدوث خطأ في الطلب، تواصلوا معنا فورًا عبر واتساب وسنحلّ الأمر بإذن الله.</p>',
            ],
            [
                'slug' => 'faq',
                'title' => 'الأسئلة الشائعة',
                'sort_order' => 4,
                'content' => '<h3>كيف أطلب كتابًا؟</h3>'
                    .'<p>اختَر الكتاب، أضِفه إلى السلة، ثم أكمل بيانات الطلب واختر طريقة الدفع المناسبة.</p>'
                    .'<h3>ما طرق الدفع المتاحة؟</h3>'
                    .'<p>الدفع يدويًا عبر إنستاباي أو فودافون كاش أو التحويل البنكي، إضافةً إلى الدفع الأونلاين عند تفعيله.</p>'
                    .'<h3>هل تشحنون خارج مصر؟</h3>'
                    .'<p>نعم، نشحن دوليًا لكل الدول. تختلف مدّة الشحن ورسومه حسب الدولة وتظهر عند إتمام الطلب.</p>',
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
