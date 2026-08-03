<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// سلة العميل المسجَّل محفوظة على الخادم لمتابعة «السلات المتروكة» (عميل أضاف كتبًا
// ولم يُكمل الطلب). صفٌّ واحد لكل عميل (customer_id فريد): items يخزّن المعرّفات
// والكميات فقط `[{"id":..,"qty":..}]` — الأسعار والعناوين تُحسَب من قاعدة البيانات
// عند العرض (بند 4.1، لا سعر مخزَّن على العميل). updated_at مفهرس لاستعلام «المتروكة
// منذ أكثر من المهلة». تُحذَف تلقائيًّا مع حذف العميل، وتُمسَح خادميًّا عند إتمام الطلب.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('items');
            $table->timestamp('updated_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_carts');
    }
};
