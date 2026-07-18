<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// سلاسل الكتب: كيان يُنشأ مرة واحدة (مثل دار النشر)، ويُسنَد إليه عدة كتب مستقلة.
// كل عنوان في السلسلة كتاب كامل بغلافه وسعره ومخزونه؛ السلسلة تجمعها وتربطها.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('slug', 200)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
