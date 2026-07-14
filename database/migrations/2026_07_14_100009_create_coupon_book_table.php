<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restrict a coupon to specific books (applies_to = 'products').
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_book', function (Blueprint $table) {
            $table->foreignId('book_id')->constrained('books')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete()->cascadeOnUpdate();

            $table->primary(['book_id', 'coupon_id']);
            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_book');
    }
};
