<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Book;
use App\Models\OrderItem;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * تغطية المخزون — يربط سرعة البيع بالمتاح ليجيب «متى ينفد هذا الكتاب». أعمق من
 * عدّاد المخزون المنخفض (ResolvesLowStock): يحسب متوسّط البيع اليومي من مبيعات آخر
 * 30 يومًا (طلبات صالحة، بنفس تعريف ScopesRevenue)، ثم أيّام التغطية = المخزون ÷
 * السرعة. يرتّب الأشدّ إلحاحًا أوّلًا (نافد ← أقلّ تغطية) ليوجّه قرار إعادة التعبئة.
 *
 * مرئيّ لمن يملك products.view (تخطيط مخزون، غير مالي). مخزَّن 5 دقائق كبقية اللوحة.
 */
class StockCoverageWidget extends Widget
{
    use CachesDashboardData;
    use ScopesRevenue;

    protected static ?int $sort = 6;

    protected static string $view = 'filament.widgets.stock-coverage';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('products.view');
    }

    protected function getViewData(): array
    {
        $rows = collect(static::rememberDashboard('stock_coverage', function (): array {
            // مبيعات آخر 30 يومًا لكل كتاب (طلبات صالحة): [book_id => كمية].
            $sold = static::scopeRevenueOrders(
                OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                'orders.status',
            )
                ->where('orders.created_at', '>=', static::trendWindowStart())
                ->groupBy('order_items.book_id')
                ->selectRaw('order_items.book_id, SUM(order_items.quantity) as qty')
                ->pluck('qty', 'order_items.book_id');

            $windowDays = max(1, self::TREND_DAYS);

            return Book::query()
                ->where('is_published', true)
                ->where('manage_stock', true)
                ->get(['id', 'title', 'stock_quantity', 'stock_status'])
                ->map(function (Book $b) use ($sold, $windowDays): array {
                    $qty = (int) ($sold[$b->id] ?? 0);
                    $velocity = $qty / $windowDays;                 // نسخة/يوم
                    $cover = $velocity > 0 ? $b->stock_quantity / $velocity : null; // أيّام، null=بلا مبيعات

                    return [
                        'title' => $b->title,
                        'stock' => (int) $b->stock_quantity,
                        'sold30' => $qty,
                        'cover' => $cover === null ? null : round($cover),
                        'out' => $b->stock_status === 'out_of_stock' || $b->stock_quantity <= 0,
                    ];
                })
                // الأشدّ إلحاحًا أوّلًا: نافد (‑1) ← أقلّ تغطية ← البطيء بلا مبيعات آخِرًا.
                ->sortBy(fn (array $r): float => $r['out'] ? -1 : ($r['cover'] ?? 1.0e9))
                ->take(12)
                ->values()
                ->all();
        }, []));

        return [
            'rows' => $rows,
            'hasData' => $rows->isNotEmpty(),
        ];
    }
}
