<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// جدول ربط المقال بالكتب ذات الصلة (روابط داخلية + ترويج + SEO).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_book', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();

            $table->primary(['article_id', 'book_id']);
            $table->index('book_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_book');
    }
};
