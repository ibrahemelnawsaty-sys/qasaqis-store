<?php

declare(strict_types=1);

use App\Support\Money;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تجاوز سعر الشحن لكل دولة + مدة التوصيل على الدول والمناطق (M8).
 *
 * البنية القائمة تسعّر بالمنطقة فقط (`shipping_zones.flat_cost`)، فلا سبيل لتسعير
 * دولة بعينها داخل منطقتها. هذان العمودان يكملان سلسلة الوراثة:
 *
 *   محافظة → دولة → منطقة        (أول قيمة غير NULL تفوز)
 *
 * `shipping_zones.flat_cost` **يبقى كما هو ولا يُحذف** — هو الرتبة الأخيرة في
 * السلسلة والمستهلَك النهائي حين لا تُخصَّص دولة ولا محافظة.
 *
 * ── الحارس الحرج ──────────────────────────────────────────────────────────────
 * ترحيل `config('egypt.shipping.flat')` إلى صف مصر يبدو بديهيًا وهو فخّ: القيمة
 * المضبوطة اليوم `SHIPPING_FLAT_COST=0.00`، ونصّ `config/egypt.php` نفسه يصفها
 * بأنها «افتراضيًا 0.00 حتى يضبطها الأدمن» — أي «لم تُضبط بعد» لا «مجاني عمدًا».
 * ونسخها حرفيًا يكتب صفرًا صريحًا يوقف الوراثة عند الرتبة الثالثة، فتتجمّد الـ27
 * محافظة على صفر مهما ضبط المالك منطقة مصر، ويعمى أي تقرير عن غير المسعَّر.
 *
 * لذلك: **لا تُكتب قيمة إلا إن كانت موجبة فعلًا** (`Money::isPositive`)، وإلا
 * يبقى العمود NULL. هذا العيب اكتشفته مراجعتان عدائيتان مستقلتان، ولا يمكن لأي
 * اختبار كشفه: تحت RefreshDatabase يكون `countries` فارغًا لحظة الهجرة فتمسّ صفر
 * صفوف — الاختبار يرى وراثة سليمة والإنتاج يرى توقّفًا عند الصفر.
 *
 * ── ترتيب النشر الملزم ────────────────────────────────────────────────────────
 * الهجرة تقرأ config() وقت التشغيل، وعلى استضافة مشتركة يكون config مخبّأً:
 *     php artisan config:clear  →  php artisan migrate  →  php artisan config:cache
 * بغيرها تقرأ الهجرة نسخة قديمة وقد تفوت قيمة مضبوطة فعلًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table): void {
            // NULL = ورِّث من المنطقة · 0.00 = مجاني عمدًا لهذه الدولة.
            $table->decimal('shipping_cost', 10, 2)->nullable()->after('shipping_zone_id');
            $table->unsignedTinyInteger('delivery_days_min')->nullable()->after('shipping_cost');
            $table->unsignedTinyInteger('delivery_days_max')->nullable()->after('delivery_days_min');
        });

        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->unsignedTinyInteger('delivery_days_min')->nullable()->after('flat_cost');
            $table->unsignedTinyInteger('delivery_days_max')->nullable()->after('delivery_days_min');
        });

        $this->migrateEgyptFlatCost();
    }

    public function down(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->dropColumn(['delivery_days_min', 'delivery_days_max']);
        });

        Schema::table('countries', function (Blueprint $table): void {
            $table->dropColumn(['shipping_cost', 'delivery_days_min', 'delivery_days_max']);
        });
    }

    /**
     * ينقل سعر الشحن المصري المضبوط في البيئة إلى صف مصر — **إن كان مضبوطًا فعلًا**.
     */
    private function migrateEgyptFlatCost(): void
    {
        $flat = config('egypt.shipping.flat');

        if ($flat === null || $flat === '') {
            return;
        }

        $normalized = Money::normalize($flat);

        // صفر = «لم يُضبط» في هذا السياق ⇒ اتركه NULL كي تبقى الوراثة حيّة.
        if (! Money::isPositive($normalized)) {
            return;
        }

        DB::table('countries')
            ->where('iso_code', 'EG')
            ->whereNull('shipping_cost')
            ->update(['shipping_cost' => $normalized]);
    }
};
