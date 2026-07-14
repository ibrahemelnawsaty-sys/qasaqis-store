<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Restrict a coupon to specific categories (applies_to = 'categories').
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_category', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete()->cascadeOnUpdate();

            $table->primary(['category_id', 'coupon_id']);
            $table->index('coupon_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_category');
    }
};
