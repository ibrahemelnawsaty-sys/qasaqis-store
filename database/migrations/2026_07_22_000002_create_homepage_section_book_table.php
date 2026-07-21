<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كتب القسم اليدوية/المثبّتة. لقسم يدوي: هي كل كتبه. لقسم تلقائي: كتب «مثبّتة» تظهر
 * أولًا ثم تُكمِل القاعدة. `position` يحدّد ترتيبها داخل القسم (يكتبه السحب في الأدمن).
 * اسم `position` لا `sort_order` عمدًا كي لا يلتبس بعمود books.sort_order عند السحب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_section_book', function (Blueprint $table) {
            $table->foreignId('homepage_section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete();
            $table->integer('position')->default(0);

            $table->primary(['homepage_section_id', 'book_id']);
            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_section_book');
    }
};
