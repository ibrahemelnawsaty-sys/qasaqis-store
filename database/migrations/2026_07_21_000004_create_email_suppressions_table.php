<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قائمة الحظر العامة للحملات: كل بريد هنا يُستبعَد من كل حملة قادمة (وقت البناء
 * ووقت الإرسال معًا). تخصّ الحملات التسويقية فقط — لا تمسّ رسائل المعاملات
 * (تأكيد طلب/تحقّق) التي يحقّ للعميل تلقّيها دائمًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_suppressions', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            // unsubscribe | bounce | manual | complaint
            $table->string('reason')->default('unsubscribe');
            $table->timestamp('suppressed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_suppressions');
    }
};
