<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بيانات إسناد التتبّع لكل طلب (M6) — جدول 1:1 لإبقاء orders نحيفًا. يلتقط
 * معرّفات المتصفح (fbp/fbc/ga_client_id) وقت الدفع لصحّة الإسناد في حدث الشراء
 * الخادمي، وpurchase_event_id لمنع ازدواج العدّ، و meta_sent_at/ga4_sent_at كحارس
 * idempotency لكل قناة، وads_consent لبوابة إرسال PII لـ Meta.
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
            $table->text('event_source_url')->nullable(); // Referer قد يكون طويلًا (fbclid/UTM).
            $table->boolean('ads_consent')->default(false); // موافقة الزائر الإعلانية وقت الدفع.
            $table->uuid('purchase_event_id'); // ثابت لإلغاء تكرار Meta/GA4.
            // ختم لكل قناة على حدة: نجاح إحداهما لا يُفقد الأخرى (تُعاد وحدها).
            $table->timestamp('meta_sent_at')->nullable();
            $table->timestamp('ga4_sent_at')->nullable();
            $table->timestamps();

            $table->index(['meta_sent_at', 'ga4_sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_tracking');
    }
};
