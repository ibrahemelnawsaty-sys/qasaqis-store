<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Migrations\Migration;

/**
 * أوصاف SEO لأقسام الكتب الستة: تُستخدم كنصّ تعريفيّ مرئيّ على صفحة القسم ووصف ميتا
 * في نتائج البحث (SeoDefaults) — يميّز صفحات الأقسام في جوجل بدل وصف عامّ. تُضبط
 * فقط للأقسام الفارغة الوصف (لا تدهس ما كتبه المالك). المطابقة بالـslug الثابت.
 */
return new class extends Migration
{
    private const DESCRIPTIONS = [
        'novels' => 'روايات أطفال ويافعين مختارة بحبّ: قصص طويلة تنمّي الخيال وحبّ القراءة وتناسب القارئ الصغير المتقدّم. تصفّح مجموعة «قصاقيص أطفال» واختر ما يناسب عمر طفلك.',
        'science' => 'كتب علمية للأطفال تبسّط العلوم والطبيعة والفضاء بأسلوب ممتع ورسوم جذّابة تغذّي فضول طفلك وحبّه للاكتشاف. مجموعة مختارة بعناية من «قصاقيص أطفال».',
        'behavior-emotions' => 'كتب السلوكيات والمشاعر للأطفال: تعلّم طفلك إدارة الغضب والخوف والمشاركة والثقة بالنفس عبر قصص محبّبة لا وعظًا مباشرًا. اختيار «قصاقيص أطفال».',
        'stories' => 'قصص أطفال مصوّرة مختارة بحبّ: حكايات قصيرة تزرع القيم وتنمّي الخيال واللغة لكل الأعمار. تصفّح مجموعة «قصاقيص أطفال» واقرأ لطفلك كل يوم.',
        'early-childhood' => 'كتب الطفولة المبكرة (٠–٥ سنوات): كتب أولى متينة بصور كبيرة وكلمات بسيطة تبني لغة طفلك وحبّه للكتاب مبكرًا. مجموعة «قصاقيص أطفال» المختارة.',
        'religious' => 'كتب دينية للأطفال بأسلوب محبّب: تعرّف طفلك على القيم والسيرة والأخلاق الإسلامية بلغة بسيطة ورسوم جميلة. اختيار «قصاقيص أطفال» بعناية.',
    ];

    public function up(): void
    {
        foreach (self::DESCRIPTIONS as $slug => $desc) {
            Category::where('slug', $slug)
                ->where(fn (Builder $q) => $q->whereNull('description')->orWhere('description', ''))
                ->update(['description' => $desc]);
        }
    }

    public function down(): void
    {
        // يُفرَّغ فقط ما لم يُعدّله المالك بعدُ (لا يزال يطابق ما ضبطناه).
        foreach (self::DESCRIPTIONS as $slug => $desc) {
            Category::where('slug', $slug)->where('description', $desc)->update(['description' => null]);
        }
    }
};
