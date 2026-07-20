<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول دفعات الطابور (Bus::batch). حملة البريد تُرسَل كدفعة تُتابَع تقدّمها
 * (نجاح/فشل/إلغاء) وتُشغّل ردّ finally لتحديث حالة الحملة. القيمة الافتراضية
 * لـ queue.batching = database فيلزم هذا الجدول (لم يُنشئه إعداد Laravel القياسي).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_batches')) {
            return;
        }

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_batches');
    }
};
