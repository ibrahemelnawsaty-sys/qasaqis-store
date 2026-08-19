<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تجاوز التسعير اليدويّ لكلّ كتاب/عملة. **يخزّن الاستثناءات فقط** — لا نُجسّد
 * التحويل الآليّ هنا (يُحسَب طيرانًا في PricingService). فائدته المزدوجة:
 *   (١) تغيير معدّل عملةٍ واحد يُعيد تسعير كلّ الكتب غير المتجاوَزة فورًا (آليّ للكلّ).
 *   (٢) صفٌّ هنا = سعرٌ ثابتٌ وضعه المالك يدويًّا لهذا الكتاب بهذه العملة (تجاوز).
 * السعر بعملة الوجهة (مثلًا 35 ريالًا)، بثلاث خانات لدعم الدينار الكويتيّ/البحرينيّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_country_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->char('currency_code', 3);
            $table->foreign('currency_code')->references('code')->on('currencies')->cascadeOnDelete();

            $table->decimal('price', 12, 3);              // بعملة الوجهة (تجاوز يدويّ)
            $table->decimal('old_price', 12, 3)->nullable(); // سعرٌ مشطوب اختياريّ
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['book_id', 'currency_code']); // تجاوز واحد لكلّ كتاب/عملة
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_country_prices');
    }
};
