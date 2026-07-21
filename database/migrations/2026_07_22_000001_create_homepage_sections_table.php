<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أقسام كتب الرئيسية (كاروسيلات) — كانت مثبّتة في HomeController، ننقلها لقاعدة
 * البيانات ليضيفها الأدمن ويحذفها ويرتّبها. كل قسم تلقائي بقاعدة (source_type) مع
 * إمكانية تعديل يدوي (كتب مثبّتة/مختارة عبر homepage_section_book).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_sections', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow', 60)->nullable();
            $table->string('title', 150);
            $table->string('subtitle', 255)->nullable();
            // latest | bestsellers | featured | popular | on_sale | category | manual
            $table->string('source_type', 20)->default('latest');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedTinyInteger('item_limit')->default(8);
            $table->string('cta_url', 255)->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('background_pattern', 40)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_sections');
    }
};
