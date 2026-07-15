<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مناطق الشحن الدولي (M5) — مُدارة من CMS. flat_cost بالجنيه المصري (التحصيل
 * يبقى EGP، الدفع يدوي مصري) ويضبطها الأدمن — لا تُخترع (بند 1.1). الأسماء
 * name_ar/name_en بيانات محرَّرة لا نصوص مثبّتة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->decimal('flat_cost', 10, 2)->default(0); // بالجنيه — يضبطها الأدمن.
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
