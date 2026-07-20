<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// تكلفة الشحن المدفوعة لشركة الشحن (المرحلة ٣ من القسم المالي).
// اسم مميّز عمدًا عن shipping_total: الأخير هو ما يُحصَّل من العميل، وهذا ما
// يُدفع للشركة — والفرق بينهما هو هامش الشحن. decimal(10,2) لا float (3.5).
//
// nullable ومقصود: NULL يعني «لم تُدخَل فاتورة الشركة بعد» ولا يساوي صفرًا (صفر
// يعني شحنًا مجّانيًا فعليًا). هامش الشحن يُحسب فقط على الطلبات معروفة هذه القيمة،
// والتاريخية تبقى NULL ولا تُخترع. لا فهرس: تقارير تُرشّح على created_at لا عليه (3.2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('carrier_cost', 10, 2)->nullable()->after('shipping_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('carrier_cost');
        });
    }
};
