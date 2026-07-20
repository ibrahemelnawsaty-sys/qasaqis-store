<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ تغيّر حالة الطلب — أثر دائم قابل للاستعلام لكل انتقال في دورة حياة الطلب.
 *
 * قبل هذا الجدول لم يكن لتغيّر الحالة أي أثر في قاعدة البيانات: الحالة السابقة
 * تُقرأ لحظيًا عبر getOriginal('status') داخل OrderObserver ثم تُفقد، والأثر
 * الوحيد سطر نصي في ملف اللوج (Log::info('orders.status_updated')) لا يُعرض في
 * اللوحة ولا يُستعلم عنه. جدول activity_log موجود لكنه يتيم (حزمة
 * spatie/laravel-activitylog غير مثبَّتة في composer.json)، فلا يصلح بديلًا.
 *
 * ملاحظات تصميمية (الباب 8.3 — التعليق يشرح «لماذا»):
 *
 * 1) from_status/to_status هما string لا enum عمدًا، رغم أن orders.status نفسه
 *    enum. جدول التاريخ يخزّن **لقطة** لقيمة كانت صحيحة وقت حدوثها؛ لو رُبط بـ
 *    enum مطابق لانكسرت الصفوف التاريخية عند أي هجرة لاحقة تحذف قيمة من enum
 *    الطلبات (MySQL يرفض/يقتطع عند ALTER). أطول قيمة حالية «processing» (10)،
 *    وطول 20 يترك هامشًا. نفس منطق لقطة order_items.book_title.
 *
 * 2) source يبقى enum كما هو محدَّد في العقد، وبلا default عمدًا: مَن يكتب الصف
 *    يجب أن يصرّح بمصدره. القيمة الخاطئة في سجل تدقيق أسوأ من خطأ صريح عند الكتابة.
 *
 * 3) created_at فقط بلا updated_at: السجل ملحق-فقط (append-only)؛ الصف لا يُعدَّل
 *    بعد كتابته، فعمود updated_at يوحي بعكس ذلك. الموديل يضبط UPDATED_AT = null.
 *
 * 4) actor_id بـ nullOnDelete لا cascadeOnDelete: حذف حساب موظف يجب ألا يمحو
 *    تاريخ الطلبات التي مسّها (يبقى الصف بفاعل مجهول). أما order_id فـ
 *    cascadeOnDelete: تاريخ طلب محذوف نهائيًا لا معنى له. تنبيه: orders تستخدم
 *    softDeletes، فالحذف الناعم لا يُفعّل الـ cascade — الصفوف تبقى مع الطلب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            // null = أول قيد للطلب (لا حالة سابقة). انظر ملاحظة (1) أعلاه.
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);

            // سبب/ملاحظة اختيارية يكتبها الأدمن أو يولّدها النظام.
            $table->string('note', 500)->nullable();

            // null = لا فاعل بشري (مهمة مجدولة/نظام)، أو حساب محذوف. انظر (4).
            $table->foreignId('actor_id')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->enum('source', ['admin', 'system', 'customer']);

            $table->timestamp('created_at')->nullable();

            // الاستعلام الوحيد المتوقَّع: خط زمني لطلب واحد مرتَّب زمنيًا
            // (RelationManager داخل صفحة الطلب) — فهرس مركّب يخدمه كاملًا،
            // ويخدم قيد المفتاح الخارجي على order_id لأنه العمود الأول فيه.
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
