<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// لقطة تكلفة الشراء وقت البيع على سطر الطلب (المرحلة ٢ من القسم المالي).
// تُجمّد التكلفة لحظة الطلب فيصير الربح ثابتًا لا يتأثر بتعديل books.cost_price
// لاحقًا ولا بحذف الكتاب (order_items.book_id هو nullOnDelete). تُحاكي زوج
// unit_price/line_total الموجود. decimal(10,2) لا float (الدستور 3.5).
//
// nullable ومقصود: كتاب بلا تكلفة مُدخلة (BOOK1) يُلتقط سطره بتكلفة NULL، ولا
// نخترع صفرًا (الدستور 0.4/1.1). لا فهرس: يُجمّع عبر order_id المفهرس، ولا
// يُستخدم في WHERE/ORDER BY (الدستور 3.2).
//
// الصفوف التاريخية (قبل هذه الهجرة) تبقى NULL ولا تُملأ من cost_price الحالي
// المتغيّر — ذلك اختراعٌ لماضٍ مجهول. تُعرض «التكلفة غير محفوظة» وتُستبعد من COGS.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
            $table->decimal('line_cost', 10, 2)->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['unit_cost', 'line_cost']);
        });
    }
};
