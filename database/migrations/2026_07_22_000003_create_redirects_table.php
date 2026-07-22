<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحويلات URL (301/302). تُنشأ تلقائيًا عند تغيير slug كتاب/مقال/صفحة (يمنع 404
 * ويحفظ ترتيب SEO والروابط الخلفية)، ويديرها الأدمن يدويًا كذلك. تُفحَص فقط عند 404.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 255)->unique();
            $table->string('to_path', 255);
            $table->unsignedSmallInteger('status_code')->default(301); // 301 دائم | 302 مؤقّت
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('source', 20)->default('manual'); // manual | auto
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
