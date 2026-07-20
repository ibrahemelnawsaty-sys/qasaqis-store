<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حملة بريدية أنشأها الأدمن (تخفيضات/موسم/إعلان/نشرة). سجل واحد لكل إرسال يحمل
 * المحتوى المعقَّم والجمهور والحالة وعدّادات التقدّم — مصدر تدقيق «من أرسل ماذا ومتى».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('template_key')->nullable();
            $table->longText('body_html');
            $table->json('audiences');
            $table->json('external_emails')->nullable();
            // draft | queued | sending | sent | failed
            $table->string('status')->default('draft');
            $table->string('batch_id')->nullable();
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaigns');
    }
};
