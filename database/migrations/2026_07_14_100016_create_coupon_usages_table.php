<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tracks each coupon redemption to enforce usage_limit / usage_limit_per_user.
// Placed after orders so the order FK resolves cleanly (no circular migration needed).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('order_id')->nullable()
                ->constrained('orders')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index('user_id'); // enforce per-user limit.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
