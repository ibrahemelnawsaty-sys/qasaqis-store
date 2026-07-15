<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ختم أول استرجاع ناجح لمخزون الطلب (M2). NULL = لم يُسترجع بعد؛ يُستخدم كمُطالبة
 * حصرية داخل RestoreOrderStockAction لمنع الاستعادة المزدوجة عند تسابق مسارين
 * (تغيير يدوي للحالة + الأمر المجدول). لا فهرس — لا يُستعلم عنه في WHERE عريض.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->timestamp('stock_restored_at')->nullable()->after('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('stock_restored_at');
        });
    }
};
