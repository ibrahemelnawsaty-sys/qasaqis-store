<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عملات العرض (تسعير متعدّد العملات). العملة الأساس EGP هي مصدر الحقيقة الماليّ
 * (الطلب/الدفع/الفوترة بالجنيه)، وبقيّة العملات طبقةُ *عرضٍ* فقط تُحوَّل من الأساس.
 *
 * جوهر فكرة المالك: `egp_per_unit` = «سعر التحويل بالجنيه للوحدة **بعد الهامش**»
 * لا السعر السوقيّ الحقيقيّ. مثال: الريال الحقيقيّ ~13ج لكنّ المالك يحوّل على 10 ⇒
 * egp_per_unit = 10، فيظهر كتابُ 300ج بـ 30 ريالًا (= 300 ÷ 10) بدل 23. الفرق
 * هو الهامش. و`reference_rate` (13) للعرض في اللوحة فقط ليرى المالك هامشه.
 *
 * صيغة العرض: displayed = round_up(base_egp ÷ egp_per_unit, rounding_step).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();          // ISO 4217: EGP/SAR/AED/USD…
            $table->string('symbol', 12);                 // ر.س / د.إ / $ (يقابل «ج.م»)
            $table->string('name_ar', 60);                // «ريال سعوديّ»

            // معدّل التحويل بالجنيه للوحدة الواحدة، **متضمّنًا هامش المالك**. القاعدة EGP=1.
            $table->decimal('egp_per_unit', 12, 4)->default(1);
            // السعر السوقيّ الحقيقيّ (اختياريّ) — لعرض الهامش في اللوحة، لا يدخل الحساب.
            $table->decimal('reference_rate', 12, 4)->nullable();

            // عدد الخانات العشريّة للعرض (2 غالبًا؛ 3 لـ KWD/BHD/OMR).
            $table->unsignedTinyInteger('decimals')->default(2);
            // خطوة التقريب لتجميل الرقم المحوّل (5 للريال/الدرهم، 0.25 للدينار…).
            $table->decimal('rounding_step', 10, 4)->default(1);
            // اتّجاه التقريب: صعودًا (لصالح الهامش) أو لأقرب خطوة.
            $table->enum('rounding_mode', ['up', 'nearest'])->default('up');

            $table->boolean('is_active')->default(true);  // إظهارها في المبدّل
            $table->boolean('is_base')->default(false);   // EGP = القاعدة (egp_per_unit=1)
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
