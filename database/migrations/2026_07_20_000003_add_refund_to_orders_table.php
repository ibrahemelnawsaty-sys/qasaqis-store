<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// مبلغ مُرتجَع جزئيًا وتاريخه (المرحلة ٤أ من القسم المالي).
// المرتجع الكلّي يُمثَّل أصلًا بحالة status='refunded' (تُستبعد من الإيراد كليًّا)؛
// أما المرتجع الجزئي على طلب محقّق فيبقى الطلب محتسبًا ويُخصم منه هذا المبلغ.
// decimal(10,2) لا float (3.5). nullable: NULL/0 = لا مرتجع.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->decimal('refunded_amount', 10, 2)->nullable()->after('grand_total');
            $table->timestamp('refunded_at')->nullable()->after('refunded_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['refunded_amount', 'refunded_at']);
        });
    }
};
