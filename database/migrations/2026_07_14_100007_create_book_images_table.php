<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Gallery/cover images for a book. Stored as file paths (WebP preferred for weak networks).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('collection', ['cover', 'gallery'])->default('gallery');
            $table->string('path', 255);
            $table->string('disk', 40)->default('public');
            $table->string('alt', 255)->nullable();
            $table->boolean('is_cover')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['book_id', 'sort_order']);
            $table->index(['book_id', 'collection']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_images');
    }
};
