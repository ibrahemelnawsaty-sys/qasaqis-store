<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * محافظات مصر كجدول حقيقي بسعر شحن قابل للتحرير من اللوحة (M8).
 *
 * اليوم تعيش المحافظات الـ27 في مصفوفة `config/egypt.php`، وسعر الشحن في متغيّر
 * بيئة واحد `SHIPPING_FLAT_COST` مع مصفوفة تجاوزات فارغة — أي أن تغيير سعر محافظة
 * واحدة يتطلّب تعديل ملف على الخادم. هذا الجدول ينقل القرار إلى المالك.
 *
 * **دلالة العمود `shipping_cost` (أساس التصميم كله):**
 *   NULL   = «لم يُضبط» ⇒ ورِّث من الرتبة الأعلى (الدولة ثم المنطقة).
 *   0.00   = «مجاني عمدًا» ⇒ توقّف، لا وراثة.
 * الخلط بينهما هو الخطأ الذي أوقفته المراجعة العدائية للخطة: نسخ `0.00` من
 * الإعدادات يحوّل «لم يُضبط بعد» إلى قرار مجانية متعمَّد، فيُجمّد الـ27 محافظة على
 * صفر ويُعمي أي تقرير عن المحافظات غير المسعّرة. لذلك تُبذر هنا بـ NULL صراحةً.
 *
 * `name_ar` فريد لأنه **مفتاح الربط الفعلي** مع `orders.governorate` (نصّ لا FK،
 * اتساقًا مع لقطة `shipping_zone_code` القائمة) ومع قائمة التحقق في CheckoutRequest.
 *
 * البذر داخل up() يتبع سابقة `2026_07_19_000001_create_trust_items_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governorates', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar', 50)->unique();
            $table->string('name_en', 50)->nullable();

            // NULL = ورِّث · 0.00 = مجاني عمدًا. لا float (بند 3.5).
            $table->decimal('shipping_cost', 10, 2)->nullable();

            // مدة التوصيل المتوقّعة — أول ما تسأل عنه الأم قبل الشراء، ولا حقل
            // لها اليوم في أي جدول. NULL هنا أيضًا = ورِّث من الرتبة الأعلى.
            $table->unsignedTinyInteger('delivery_days_min')->nullable();
            $table->unsignedTinyInteger('delivery_days_max')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $this->seedFromConfig();
    }

    public function down(): void
    {
        Schema::dropIfExists('governorates');
    }

    /**
     * تبذر المحافظات من `config/egypt.php` بالترتيب نفسه، **بلا أي سعر**.
     *
     * القراءة من config لا من قائمة مكتوبة هنا: تبقى المصفوفة مصدر الحقيقة الوحيد
     * أثناء تعايش الملفين، فلا ينشأ اختلاف صامت بين ما يقبله التحقق وما يُسعَّر.
     */
    private function seedFromConfig(): void
    {
        /** @var array<int, string> $names */
        $names = (array) config('egypt.governorates', []);

        if ($names === []) {
            // config مخبّأ بنسخة أقدم من مفتاح governorates، أو ملف ناقص. الصمت هنا
            // ينتج جدولًا فارغًا يكسر كل طلب مصري لاحقًا — فنفشل بصوت عالٍ بدلًا منه.
            throw new RuntimeException(
                'config(egypt.governorates) فارغة — شغّل php artisan config:clear قبل الهجرة.'
            );
        }

        $now = now();
        $rows = [];

        foreach (array_values($names) as $i => $name) {
            $rows[] = [
                'name_ar' => $name,
                'name_en' => null,
                'shipping_cost' => null, // «لم يُضبط» — يضبطه المالك من اللوحة.
                'delivery_days_min' => null,
                'delivery_days_max' => null,
                'is_active' => true,
                'sort_order' => $i,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('governorates')->insert($rows);
    }
};
