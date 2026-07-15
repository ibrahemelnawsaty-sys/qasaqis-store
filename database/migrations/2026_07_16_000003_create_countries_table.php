<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الدول المدعومة للشحن (M5) — مُدارة من CMS. كل دولة مرتبطة بمنطقة شحن تحدّد
 * تكلفتها. تُنشأ بعد shipping_zones (FK). restrictOnDelete: لا تُحذف منطقة بها دول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->char('iso_code', 2)->unique(); // ISO 3166-1 alpha-2 (EG, SA…).
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('dial_code', 8)->nullable(); // +20, +966…
            $table->foreignId('shipping_zone_id')
                ->constrained('shipping_zones')->restrictOnDelete()->cascadeOnUpdate();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
