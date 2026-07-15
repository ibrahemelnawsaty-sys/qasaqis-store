<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول المقالات (المدونة). المحتوى HTML قادم من محرر نصي (RichEditor).
// المخطط الموحّد للمدونة — يُلتزم به بدقة كما هو معرّف في مهمة الباك-إند.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();

            $table->string('title', 200);
            $table->string('slug', 220)->unique(); // unique() ينشئ الفهرس تلقائيًا — لا تكرّره.

            $table->text('excerpt')->nullable();
            $table->longText('content'); // HTML من محرر نصي.

            // NULL => عنصر بديل محايد (لا غلاف مخترع — بند 0.4 / 1.1).
            $table->string('cover_image', 255)->nullable();
            $table->string('author_name', 150)->nullable();

            // القسم نصّي حرّ (نصائح تربوية، تربية بالقصص، مراجعات كتب، أنشطة وتعليم...).
            $table->string('category', 100);

            $table->unsignedSmallInteger('reading_minutes')->default(0);

            $table->string('seo_title', 255)->nullable();
            $table->string('seo_description', 320)->nullable();

            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            // فهرس مركّب يخدم قائمة المدونة العامة (المنشور + الأحدث نشرًا).
            $table->index(['is_published', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
