<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس مركّبة لاستعلامات لوحة العمليات الأكثر تكرارًا.
 *
 * - orders(created_at, status): قُمع الحالات «WHERE created_at >= ? GROUP BY status»
 *   يُنفَّذ في كلّ تحميل صفحة؛ الفهرس المركّب يجعله مسحًا مُغطّى (كلا العمودين في الفهرس)
 *   بدل مسح المدى على created_at وحده ثمّ الرجوع للصفّ لقراءة status.
 * - order_items(order_id, book_id): وصلات الإيراد الثلاث (الأكثر مبيعًا/التغطية/الأقسام)
 *   تربط order_items عبر order_id وتقرأ book_id للتجميع.
 *
 * جدولا orders/order_items يملكان أصلًا فهارس مفردة على هذه الأعمدة؛ لا نُسقطها
 * (فهرس المفتاح الأجنبي مطلوب للقيد)، بل نضيف المركّبة. مُحصَّن ضدّ التكرار.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasIndex('orders', 'orders_created_at_status_index')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(['created_at', 'status'], 'orders_created_at_status_index');
            });
        }

        if (! $this->hasIndex('order_items', 'order_items_order_id_book_id_index')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->index(['order_id', 'book_id'], 'order_items_order_id_book_id_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->hasIndex('orders', 'orders_created_at_status_index')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropIndex('orders_created_at_status_index');
            });
        }

        if ($this->hasIndex('order_items', 'order_items_order_id_book_id_index')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->dropIndex('order_items_order_id_book_id_index');
            });
        }
    }

    /** فحص وجود فهرس بالاسم دون افتراض توفّر Schema::hasIndex في هذه النسخة. */
    private function hasIndex(string $table, string $index): bool
    {
        foreach (Schema::getIndexes($table) as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
