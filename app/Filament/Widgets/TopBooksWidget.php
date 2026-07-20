<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\BookResource;
use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ResolvesLowStock;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Book;
use App\Models\OrderItem;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * What is actually selling, over the same 30-day window as the revenue trend, so
 * the two widgets can be read together.
 *
 * Gated on «orders.view_financials»: this reports takings per title. The margin
 * column needs «products.view» on top, and books.cost_price is not selected from
 * the database at all without it — the migration marks that column «hidden from
 * support role», so it must not travel to a role that cannot see products, not
 * merely be hidden in the markup (constitution 4.4 / anti-pattern 13).
 *
 * Books with no cost_price show «—» in the margin column. BOOK1-style gaps in the
 * catalogue are real and are never filled with a guessed figure (constitution
 * 0.4 / anti-pattern 21) — an invented cost would produce an invented profit.
 *
 * Stock is shown here because these are the titles whose stock matters most: a
 * best-seller running out costs more than a slow title running out. See the note
 * on PendingActionsWidget for why low stock is not a row in the action list.
 */
class TopBooksWidget extends TableWidget
{
    use CachesDashboardData;
    use ResolvesLowStock;
    use ScopesRevenue;

    /** Deferred: the heaviest aggregate on the dashboard should not block paint. */
    protected static bool $isLazy = true;

    protected static ?int $sort = 4;

    /**
     * Five rows. The catalogue is 23 books (constitution 0.3), so five is the
     * head of the distribution and still readable at a glance; a longer list
     * turns a signal into a report.
     */
    protected const TOP_BOOKS_LIMIT = 5;

    /**
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('orders.view_financials') ?? false;
    }

    public function table(Table $table): Table
    {
        $sales = static::topSellingBooks();
        $showMargin = static::canSeeCost();

        return $table
            ->query($this->topBooksTableQuery(array_keys($sales), $showMargin))
            ->paginated(false)
            ->heading('الأكثر مبيعًا — آخر '.self::TREND_DAYS.' يومًا')
            ->description('أعلى '.self::TOP_BOOKS_LIMIT.' كتب بالكمية المباعة، دون الطلبات الملغاة والمرفوضة.')
            ->columns([
                TextColumn::make('title')
                    ->label('الكتاب')
                    ->weight('bold')
                    ->wrap()
                    // Nullable in the schema — renders nothing when absent rather
                    // than attributing an author that was never recorded.
                    ->description(fn (Book $record): ?string => $record->author),

                TextColumn::make('sold_quantity')
                    ->label('الكمية المباعة')
                    ->alignEnd()
                    ->state(fn (Book $record): int => $sales[$record->getKey()]['qty'] ?? 0),

                TextColumn::make('sold_revenue')
                    ->label('الإيراد')
                    ->money('EGP')
                    ->alignEnd()
                    ->state(fn (Book $record): string => $sales[$record->getKey()]['revenue'] ?? Money::ZERO),

                TextColumn::make('margin')
                    ->label('الهامش')
                    ->money('EGP')
                    ->alignEnd()
                    ->placeholder('—')
                    ->visible($showMargin)
                    ->state(fn (Book $record): ?string => static::margin($record, $sales[$record->getKey()] ?? null))
                    ->color(fn (Book $record): string => static::marginColor(static::margin($record, $sales[$record->getKey()] ?? null)))
                    ->description(fn (Book $record): ?string => static::marginPercentLabel($record, $sales[$record->getKey()] ?? null)),

                TextColumn::make('stock_quantity')
                    ->label('المخزون المتبقي')
                    ->badge()
                    ->alignEnd()
                    // manage_stock = false means stock is not tracked for this
                    // title (PlaceOrderAction::assertStockAndReserve skips it), so
                    // a number here would be meaningless rather than reassuring.
                    ->state(fn (Book $record): string => $record->manage_stock
                        ? (string) $record->stock_quantity
                        : 'غير متتبَّع')
                    ->color(fn (Book $record): string => static::stockColor($record)),
            ])
            ->recordUrl(
                fn (Book $record): ?string => BookResource::canEdit($record)
                    ? BookResource::getUrl('edit', ['record' => $record])
                    : null
            )
            ->emptyStateIcon('heroicon-o-chart-bar')
            ->emptyStateHeading('لا مبيعات في آخر '.self::TREND_DAYS.' يومًا')
            ->emptyStateDescription('لم يُسجَّل أي بند طلب في هذه الفترة.');
    }

    /**
     * Re-reads the ranked ids as Book models.
     *
     * The GROUP BY over order_items is cached; what runs per render is a
     * primary-key lookup of five ids, because Filament's table layer needs a live
     * Builder (vendor/filament/tables/src/Concerns/HasRecords.php:86).
     *
     * FIELD() replays the cached ranking without a second aggregate. It is raw —
     * MySQL 8 is the only supported engine (constitution 0.6) — but every id is
     * bound, never interpolated (constitution 2.5 / anti-pattern 8).
     *
     * @param  array<int, int>  $rankedIds
     */
    protected function topBooksTableQuery(array $rankedIds, bool $withCost): Builder
    {
        $columns = ['id', 'title', 'author', 'stock_quantity', 'manage_stock'];

        if ($withCost) {
            $columns[] = 'cost_price';
        }

        $query = Book::query()->select($columns)->whereKey($rankedIds);

        if ($rankedIds === []) {
            return $query;
        }

        return $query->orderByRaw(
            'FIELD(books.id, '.implode(', ', array_fill(0, count($rankedIds), '?')).')',
            $rankedIds,
        );
    }

    /**
     * Units and takings per book over the trend window, best first.
     *
     * One grouped query, no N+1: the per-row figures below are read from this
     * array, never re-queried per book (constitution 2.5 / anti-pattern 7).
     *
     * order_items.book_id is nullable — a line survives its book being deleted —
     * so null ids are dropped rather than aggregated into a phantom title. The
     * orders join carries an explicit deleted_at filter because the Order model's
     * SoftDeletes global scope does not reach a manually joined table.
     *
     * Ordering breaks ties on revenue then id so the list does not reshuffle
     * between cache refreshes for no reason.
     *
     * @return array<int, array{qty: int, revenue: string}>
     */
    protected static function topSellingBooks(): array
    {
        return static::rememberDashboard(
            'topbooks.'.self::TREND_DAYS.'.'.self::TOP_BOOKS_LIMIT,
            static function (): array {
                $rows = static::scopeRevenueOrders(
                    OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                    'orders.status',
                )
                    ->whereNull('orders.deleted_at')
                    ->whereNotNull('order_items.book_id')
                    ->where('orders.created_at', '>=', static::trendWindowStart())
                    ->where('orders.created_at', '<', Carbon::today()->addDay())
                    ->groupBy('order_items.book_id')
                    ->selectRaw(
                        'order_items.book_id as book_id,'
                        .' SUM(order_items.quantity) as sold_qty,'
                        .' SUM(order_items.line_total) as sold_revenue'
                    )
                    ->orderByDesc('sold_qty')
                    ->orderByDesc('sold_revenue')
                    ->orderBy('order_items.book_id')
                    ->limit(self::TOP_BOOKS_LIMIT)
                    ->get();

                $sales = [];

                foreach ($rows as $row) {
                    $sales[(int) $row->book_id] = [
                        'qty' => (int) $row->sold_qty,
                        'revenue' => Money::normalize($row->sold_revenue),
                    ];
                }

                return $sales;
            },
            [],
        );
    }

    /**
     * الإيراد ناقص التكلفة، بحساب عشري (bcmath) لا float (بند 3.5).
     * null عندما لا تكلفة مسجَّلة — لا تُخترع قيمة (بند 1.1).
     *
     * @param  array{qty: int, revenue: string}|null  $sale
     */
    protected static function margin(Book $record, ?array $sale): ?string
    {
        if ($sale === null || $record->cost_price === null) {
            return null;
        }

        return Money::sub(
            $sale['revenue'],
            Money::multiplyByQty((string) $record->cost_price, $sale['qty']),
        );
    }

    /**
     * نسبة الهامش إلى الإيراد — تُظهر ما إذا كان الرقم الكبير هامشًا حقيقيًا.
     *
     * @param  array{qty: int, revenue: string}|null  $sale
     */
    protected static function marginPercentLabel(Book $record, ?array $sale): ?string
    {
        $margin = static::margin($record, $sale);

        if ($margin === null || $sale === null || ! Money::isPositive($sale['revenue'])) {
            return null;
        }

        $percent = bcdiv(bcmul($margin, '100', Money::SCALE), $sale['revenue'], 0);

        return $percent.'٪ من الإيراد';
    }

    protected static function marginColor(?string $margin): string
    {
        if ($margin === null) {
            return 'gray';
        }

        // Selling below cost is the one number on this dashboard that must shout.
        return Money::isPositive($margin) ? 'success' : 'danger';
    }

    protected static function stockColor(Book $record): string
    {
        if (! $record->manage_stock) {
            return 'gray';
        }

        if ($record->stock_quantity <= 0) {
            return 'danger';
        }

        return $record->stock_quantity <= self::LOW_STOCK_THRESHOLD ? 'warning' : 'success';
    }

    private static function canSeeCost(): bool
    {
        return auth()->user()?->can('products.view') ?? false;
    }
}
