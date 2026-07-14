<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique(); // cod/instapay/vodafone_cash/bank_transfer/online_gateway.
            $table->string('name', 80);
            $table->enum('type', ['cash_on_delivery', 'manual_transfer', 'online_gateway']);
            $table->boolean('is_enabled')->default(true);
            $table->text('instructions')->nullable();
            $table->json('account_details')->nullable(); // wallet/account/IBAN (non-secret).
            $table->string('gateway_provider', 40)->nullable(); // paymob/kashier.
            $table->json('config')->nullable(); // non-sensitive config; secrets live in .env / encrypted settings.
            $table->boolean('requires_proof')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
