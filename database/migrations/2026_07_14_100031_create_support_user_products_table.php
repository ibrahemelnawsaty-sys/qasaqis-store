<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restricts a "support" user to specific books or whole categories (enforced server-side
// via Policy). A row with book_id = access to one book; a row with category_id = whole category.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_user_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('book_id')->nullable()
                ->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('category_id')->nullable()
                ->constrained('categories')->cascadeOnDelete()->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['user_id', 'book_id']);
            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_user_products');
    }
};
