<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مستلم واحد داخل حملة. يحمل توكن إلغاء اشتراك سرّيًا (48 حرفًا) هو المفتاح الوحيد
 * لصفحة الإلغاء — لا حاجة لجلسة. القيد الفريد (campaign,email) يمنع الازدواج على
 * مستوى قاعدة البيانات لا الذاكرة فقط.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            // customer | panel_user | external
            $table->string('source');
            $table->char('token', 48)->unique();
            // queued | sent | failed | unsubscribed
            $table->string('status')->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['email_campaign_id', 'email']);
            $table->index(['email_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_recipients');
    }
};
