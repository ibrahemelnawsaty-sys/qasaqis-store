<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manual transfer proof uploads. Files are stored OUTSIDE the public path (protected disk).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('payment_id')->nullable()
                ->constrained('payments')->nullOnDelete()->cascadeOnUpdate();
            $table->string('method_code', 40); // instapay/vodafone_cash/bank_transfer.
            $table->string('file_path', 255);   // random safe filename on a protected disk.
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('sender_reference', 120)->nullable();
            $table->enum('review_status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 300)->nullable();
            $table->timestamps();

            $table->index('review_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
