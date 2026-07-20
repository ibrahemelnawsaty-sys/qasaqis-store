<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ربط الطلب بحساب عميل — العمود الذي يقوم عليه كامل نطاق /account.
 *
 * ملاحظات تصميمية (الباب 8.3 — التعليق يشرح «لماذا»):
 *
 * 1) عمود جديد مستقل تمامًا عن orders.user_id القائم، ولا يُمسّ الأخير. user_id
 *    يشير إلى users (الإداريين) ويبقى NULL في كل طلبات المتجر؛ خلط المعنيين في
 *    عمود واحد يجعل صفًا واحدًا خاطئًا يمنح عميلًا هوية إدارية.
 *
 * 2) nullOnDelete لا cascadeOnDelete: حذف حساب العميلة يجب ألا يمحو طلباتها —
 *    الطلب سجل مالي/محاسبي يخصّ المتجر أيضًا، ويبقى قابلًا للتتبّع كطلب ضيف عبر
 *    orders.track. تنبيه: orders و customers كلاهما softDeletes، والحذف الناعم
 *    لا يُفعّل nullOnDelete إطلاقًا (لا حذف فعلي على مستوى قاعدة البيانات).
 *
 * 3) الفهرس المركّب (customer_id, created_at) يُسجَّل **قبل** المفتاح الخارجي عمدًا:
 *    InnoDB ينشئ فهرسًا تلقائيًا لعمود المفتاح الخارجي فقط إن لم يوجد فهرس هذا
 *    العمود أوّلُ أعمدته. بتسجيل المركّب أولًا يتبنّاه المحرّك للمفتاح الخارجي،
 *    فنحصل على فهرس واحد يخدم القيد **و** الاستعلام الوحيد المتوقَّع في
 *    /account/orders (طلبات عميلة واحدة مرتّبة بالأحدث) بدل فهرسين.
 *
 * 4) claimed_at: ختم لحظة ربط طلب سابق بالحساب عبر orders.claim. أثر دائم في
 *    قاعدة البيانات لا في ملف اللوج وحده — عملية تغيير ملكية تحتاج أثرًا قابلًا
 *    للاستعلام عند أي نزاع (الباب 4.7). يبقى NULL للطلبات التي وُلدت مرتبطة
 *    أصلًا (العميلة كانت مسجّلة الدخول وقت الشراء)، فيميّز المسارين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('user_id');
            $table->timestamp('claimed_at')->nullable()->after('customer_id');

            // يُسجَّل قبل المفتاح الخارجي — انظر الملاحظة (3).
            $table->index(['customer_id', 'created_at']);

            $table->foreign('customer_id')
                ->references('id')->on('customers')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            // الترتيب إلزامي: القيد يعتمد على الفهرس، والفهرس على العمود.
            $table->dropForeign(['customer_id']);
            $table->dropIndex(['customer_id', 'created_at']);
            $table->dropColumn(['customer_id', 'claimed_at']);
        });
    }
};
