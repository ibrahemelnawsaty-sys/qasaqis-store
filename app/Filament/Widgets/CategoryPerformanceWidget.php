<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\OrderItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * أداء الأقسام — أيّ أقسام الكتالوج تُحرِّك الطلب (المرحلة ٢). يجمع مبيعات آخر
 * 30 يومًا حسب القسم من order_items، بنفس تعريف «الطلب الصالح» وتخزين اللوحة
 * المشترك (ScopesRevenue + CachesDashboardData) اتّساقًا مع بقية الودجت.
 *
 * الكمية (طلب فعلي) تظهر لمن يملك products.view؛ الإيراد لمن يملك
 * orders.view_financials فقط (بند 4.4). البيانات المخزَّنة لا تحمل مكوّنًا خاصًّا
 * بالمستخدم — حجب الإيراد يقع وقت العرض لا في التخزين.
 */
class CategoryPerformanceWidget extends Widget
{
    use CachesDashboardData;
    use ScopesRevenue;

    protected static ?int $sort = 5;

    protected static string $view = 'filament.widgets.category-performance';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('products.view');
    }

    protected function getViewData(): array
    {
        $rows = collect(static::rememberDashboard('category_performance', function (): array {
            return static::scopeRevenueOrders(
                OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                'orders.status',
            )
                ->join('books', 'books.id', '=', 'order_items.book_id')
                ->leftJoin('categories', 'categories.id', '=', 'books.category_id')
                ->where('orders.created_at', '>=', static::trendWindowStart())
                ->groupBy('categories.id', 'categories.name')
                ->select([
                    DB::raw("COALESCE(categories.name, 'بلا قسم') as name"),
                    DB::raw('SUM(order_items.quantity) as qty'),
                    DB::raw('SUM(order_items.line_total) as revenue'),
                ])
                ->orderByDesc('revenue')
                ->get()
                ->all();
        }, []));

        return [
            'rows' => $rows,
            'maxRevenue' => (float) ($rows->max('revenue') ?: 1),
            'maxQty' => (float) ($rows->max('qty') ?: 1),
            'canFinancials' => (bool) auth()->user()?->can('orders.view_financials'),
            'hasData' => $rows->isNotEmpty(),
        ];
    }
}
