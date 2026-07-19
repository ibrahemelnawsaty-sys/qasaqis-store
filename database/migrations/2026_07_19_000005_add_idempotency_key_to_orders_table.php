<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مفتاح منع تكرار الطلب (M7 — رحلة العميل، المرحلة 5).
 *
 * يُولَّد مرة واحدة عند عرض صفحة الدفع ويُرسَل مع النموذج، فالنقر المزدوج على زر
 * «تأكيد الطلب» — شائع جدًا على الشبكات المصرية البطيئة حين لا يستجيب الزر فورًا —
 * لا يُنشئ طلبًا ثانيًا ولا يخصم المخزون مرتين.
 *
 * الفهرس الفريد هنا هو الضمان الحقيقي وليس ترفًا: الفحص المسبق في PHP وحده يترك
 * نافذة سباق بين طلبين متزامنين يمرّان معًا قبل أن يكتب أيّهما. القيد على مستوى
 * قاعدة البيانات يُسقط الثاني، ويتعامل PlaceOrderAction مع الاستثناء بإرجاع الطلب
 * الأول بدل إظهار خطأ للعميلة.
 *
 * nullable عمدًا: الطلبات القائمة تبقى بلا مفتاح (MySQL يسمح بتكرار NULL في الفهرس
 * الفريد)، وأي مسار لا يمرّر مفتاحًا يظل عاملًا بلا حماية بدل أن ينكسر.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->char('idempotency_key', 36)->nullable()->unique()->after('order_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
