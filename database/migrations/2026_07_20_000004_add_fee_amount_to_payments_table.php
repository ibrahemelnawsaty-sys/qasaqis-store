<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// رسوم معالجة الدفع لكل عملية (المرحلة ٤ب): ما تقتطعه البوابة (Paymob/Kashier)
// أو رسوم تحصيل COD. تُطرح لاحقًا لحساب صافي الربح بعد الرسوم. decimal(10,2)
// لا float (3.5). nullable: NULL = لم تُدخَل الرسوم بعد (لا صفر مفترض).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->decimal('fee_amount', 10, 2)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('fee_amount');
        });
    }
};
