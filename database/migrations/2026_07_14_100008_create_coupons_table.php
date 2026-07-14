<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('description', 200)->nullable();
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2); // 10 = 10% or 10 EGP depending on type.
            $table->decimal('min_order_total', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable(); // cap for percentage.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('used_count')->default(0); // denormalised counter.
            $table->enum('applies_to', ['all', 'categories', 'products'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->boolean('free_shipping')->default(false);
            $table->timestamps();

            $table->index('is_active');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
