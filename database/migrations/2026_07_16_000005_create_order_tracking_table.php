<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات إسناد التتبّع لكل طلب (M6) — جدول 1:1 لإبقاء orders نحيفًا. يلتقط
 * معرّفات المتصفح (fbp/fbc/ga_client_id) وقت الدفع لصحّة الإسناد في حدث الشراء
 * الخادمي، وpurchase_event_id لمنع ازدواج العدّ، وpurchase_sent_at كحارس idempotency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_tracking', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('fbp', 100)->nullable();
            $table->string('fbc', 191)->nullable();
            $table->string('ga_client_id', 100)->nullable();
            $table->string('ga_session_id', 100)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('event_source_url', 255)->nullable();
            $table->uuid('purchase_event_id'); // ثابت لإلغاء تكرار Meta/GA4.
            $table->timestamp('purchase_sent_at')->nullable();
            $table->timestamps();

            $table->index('purchase_sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking');
    }
};
