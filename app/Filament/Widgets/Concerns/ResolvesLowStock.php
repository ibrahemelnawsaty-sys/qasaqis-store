<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\Book;

/**
 * One definition of «low stock» for the dashboard.
 *
 * PendingActionsWidget counts these titles; TopBooksWidget colours the stock
 * column of each best-seller by the same threshold. Sharing the constant keeps
 * the count and the colours telling the same story.
 *
 * Titles with manage_stock = false are excluded everywhere: their stock is not
 * tracked at checkout at all (PlaceOrderAction::assertStockAndReserve skips
 * them), so a low number on such a book means nothing and would send the owner
 * chasing a non-problem.
 *
 * books.stock_quantity carries no index. That is deliberate: the catalogue is
 * 23 books (constitution 0.3), so the scan is cheaper than maintaining an index
 * for it, and the result is cached for five minutes regardless.
 */
trait ResolvesLowStock
{
    use CachesDashboardData;

    /** حدّ «المخزون المنخفض» للكتب التي يُدار مخزونها (يشمل النافد: 0). */
    public const LOW_STOCK_THRESHOLD = 5;

    /** عدد الكتب المنشورة التي يُدار مخزونها وقارب على النفاد (أو نفد). */
    protected static function lowStockCount(): int
    {
        return static::rememberDashboard(
            'lowstock.count',
            static fn (): int => Book::query()
                ->where('manage_stock', true)
                ->where('is_published', true)
                ->where('stock_quantity', '<=', static::LOW_STOCK_THRESHOLD)
                ->count(),
            0,
        );
    }
}
