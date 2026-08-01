<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ترتيب ظهور الكتب داخل كل قسم (مستقلّ لكل قسم — الكتاب قد يكون في أكثر من قسم).
// طبقة ترتيب فوق العضويّة القائمة (books.category_id الرئيسي + book_category الإضافي):
// لا تمسّ العضويّة، تخزّن فقط موضع الكتاب داخل القسم. تُملأ عند فتح القسم في الأدمن
// (SyncCategoryBookOrder) وتُستخدم كترتيب افتراضيّ لصفحة القسم في المتجر.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_book_positions', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unsignedInteger('position')->default(0);

            $table->primary(['category_id', 'book_id']);
            // يخدم ترتيب صفحة القسم (WHERE category_id ORDER BY position).
            $table->index(['category_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_book_positions');
    }
};
