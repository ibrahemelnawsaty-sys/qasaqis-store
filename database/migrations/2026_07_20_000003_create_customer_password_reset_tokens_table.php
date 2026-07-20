<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رموز استعادة كلمة مرور العملاء (M8) — منفصل عن جدول الإداريين
 * `password_reset_tokens` كي لا يختلط حارسان بمزوّدَين مختلفين.
 *
 * مفتاحه البريد (كما يفترض وسيط passwords.customers القياسي)؛ ولذلك جُعل البريد
 * إلزاميًا وفريدًا في جدول customers: البريد هو قناة الاسترداد الوحيدة المتاحة
 * فعليًا اليوم (لا مزوّد SMS/WhatsApp API في المشروع).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_password_reset_tokens');
    }
};
