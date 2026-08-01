<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهرس مستقلّ على sort_order.
 *
 * قائمة أدمن الكتب (BookResource) تفرز افتراضيًّا بـ `ORDER BY sort_order` بلا أيّ شرط
 * WHERE. الفهرس المركّب [is_published, is_featured, sort_order] لا يخدم هذا الفرز لأنّ
 * sort_order عموده الثالث (يلزم تساوٍ على العمودين الأوّلين). فينتج filesort على كامل
 * جدول books عند كلّ تحميل — صار ثقيلًا بعد نموّ الكتالوج إلى ١٦٠٠+ كتابًا، خصوصًا مع
 * SELECT * الذي يوسّع صفوف الفرز بأعمدة long_description/search_index الثقيلة فتتسرّب
 * إلى قرص مؤقّت على الاستضافة المشتركة.
 *
 * بالفهرس يصير `ORDER BY sort_order LIMIT N` مسحًا مرتّبًا للفهرس يتوقّف بعد N مدخلًا.
 * InnoDB يُلحق المفتاح الأساسيّ id ضمنيًّا بالفهرس الثانويّ، فيصير الترتيب فعليًّا
 * (sort_order, id) — يحسم تعادل القيم المتساوية ويجعل الترقيم حتميًّا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->index('sort_order', 'books_sort_order_index');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropIndex('books_sort_order_index');
        });
    }
};
