<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// استهداف الكوبون بالجمهور (فوق استهداف المنتجات applies_to): audience يحدّد «من يستحقّه»
//  all=الجميع · specific=عميل محدّد (customer_id) · new=أوّل شراء · returning=عائد ·
//  lapsed=متوقّف منذ lapsed_days · vip=صرف ≥ min_spent أو طلبات ≥ min_orders.
// الصفوف القائمة تأخذ 'all' (لا تغيير في سلوكها). سجلّ العميل يُحسَب من جدول الطلبات
// (Order::realisedRevenue) لا من عمودَي customers الميّتين.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->string('audience', 20)->default('all')->after('applies_to')->index();
            $table->foreignId('customer_id')->nullable()->after('audience')->constrained()->nullOnDelete();
            $table->unsignedInteger('lapsed_days')->nullable()->after('customer_id');
            $table->unsignedInteger('min_orders')->nullable()->after('lapsed_days');
            $table->decimal('min_spent', 10, 2)->nullable()->after('min_orders');
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropColumn(['audience', 'lapsed_days', 'min_orders', 'min_spent']);
        });
    }
};
