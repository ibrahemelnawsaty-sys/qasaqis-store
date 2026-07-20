<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// دفتر المصروفات التشغيلية (المرحلة ٤ج): إعلانات، رواتب، تغليف، إيجار…
// يُحوّل الداشبورد من «هامش المساهمة» إلى «صافي ربح النشاط» = المساهمة − المصروفات.
// المبلغ decimal(10,2) لا float (3.5). incurred_on تاريخ الصرف (يُجمّع بالنطاق).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 60);           // فئة حرة يختارها الأدمن (إعلانات، رواتب…).
            $table->string('title', 200);
            $table->decimal('amount', 10, 2);
            $table->date('incurred_on');              // تاريخ حدوث المصروف.
            $table->text('note')->nullable();
            // من سجّل المصروف — للمراجعة (4.7). يبقى الصف عند حذف المستخدم.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // النطاق الزمني هو محور تقارير المالية، والفئة للتقسيم (3.2).
            $table->index('incurred_on');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
