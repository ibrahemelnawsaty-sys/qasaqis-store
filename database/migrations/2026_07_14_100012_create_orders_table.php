<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique(); // e.g. QSQ-2026-000123.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate(); // guest checkout allowed.

            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'shipped',
                'delivered', 'completed', 'cancelled', 'refused', 'refunded',
            ])->default('pending');

            $table->string('customer_name', 150);
            $table->string('customer_phone', 20);
            $table->string('customer_phone_alt', 20)->nullable();
            $table->string('customer_email', 191)->nullable();
            $table->string('governorate', 50);
            $table->string('city', 80)->nullable();
            $table->string('address_line', 300);
            $table->string('address_notes', 300)->nullable();

            // Money — DECIMAL(10,2) only.
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('shipping_total', 10, 2)->default(0);
            $table->decimal('grand_total', 10, 2);

            $table->foreignId('coupon_id')->nullable()
                ->constrained('coupons')->nullOnDelete()->cascadeOnUpdate();
            $table->string('coupon_code', 50)->nullable(); // snapshot at order time.

            $table->enum('payment_method', ['cod', 'instapay', 'vodafone_cash', 'bank_transfer', 'online_gateway']);
            $table->enum('payment_status', ['unpaid', 'pending_review', 'partially_paid', 'paid', 'refunded', 'failed'])->default('unpaid');

            $table->string('shipping_company', 50)->nullable();
            $table->string('tracking_number', 80)->nullable();
            $table->timestamp('whatsapp_confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            $table->text('customer_note')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('payment_status');
            $table->index('payment_method');
            $table->index('customer_phone');
            $table->index('governorate');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
