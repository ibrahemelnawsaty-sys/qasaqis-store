<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('payment_method_code', 40);
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'pending_review', 'completed', 'failed', 'refunded'])->default('pending');
            $table->string('transaction_ref', 120)->nullable();
            $table->json('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('transaction_ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
