<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\BookResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\Concerns\ResolvesLowStock;
use App\Filament\Widgets\Concerns\ResolvesPendingActions;
use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The owner's to-do list: orders that are waiting on a decision, oldest first.
 *
 * Two things land here (defined once in ResolvesPendingActions so the stat card
 * above cannot disagree with this list):
 *   • a manual-payment proof awaiting review — money sitting undecided;
 *   • an order left «pending» for more than a day — a customer left hanging.
 *
 * Gated on «orders.view» (RolePermissionSeeder §3.4) — the weakest permission
 * that still justifies seeing an order exists. The «الإجمالي» column additionally
 * requires «orders.view_financials», and the amount is not even selected from the
 * database without it, so a role without financial access never receives the
 * figure — not merely a hidden column (constitution 4.4 / anti-pattern 13).
 *
 * WHY LOW STOCK IS A HEADER LINK, NOT ROWS: Filament v3 binds a table to exactly
 * one Eloquent model — getTableQuery() is typed Builder|Relation|null
 * (vendor/filament/tables/src/Concerns/InteractsWithTable.php:285) and
 * getTableRecords() runs it live (HasRecords.php:86). Books and orders cannot
 * share one typed query without a UNION hydrated into a lying carrier model, and
 * a live UNION could not sit behind the mandatory five-minute cache either. So
 * stock gets a counted link here, and per-book stock is shown as a column on
 * TopBooksWidget where the books are already loaded. Flagged in the handoff.
 */
class PendingActionsWidget extends TableWidget
{
    use ResolvesLowStock;
    use ResolvesPendingActions;

    /** Deferred so the dashboard paints before this query runs. */
    protected static bool $isLazy = true;

    protected static ?int $sort = 3;

    /**
     * Full width: five columns squeezed into half a screen would wrap on the
     * phones this panel is mostly opened on (constitution 1.6).
     *
     * @var int|string|array<string, int|null>
     */
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('orders.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->pendingActionsTableQuery())
            // At most PENDING_PREVIEW_LIMIT rows — a dashboard prompt, not a work
            // queue. Paginator chrome on eight rows is noise.
            ->paginated(false)
            ->heading('يحتاج إجراءً الآن')
            // Neutral phrasing, no imperative: the storefront addresses mothers
            // («ابحثي»), but the panel's reader is whoever owns the shop.
            ->description('الأقدم أولًا — أعلى القائمة هو الأكثر إلحاحًا.')
            ->headerActions($this->lowStockHeaderActions())
            ->columns([
                TextColumn::make('order_number')
                    ->label('رقم الطلب')
                    ->weight('bold'),

                TextColumn::make('customer_name')
                    ->label('العميل')
                    ->wrap(),

                TextColumn::make('pending_reason')
                    ->label('السبب')
                    ->badge()
                    // Derived from columns already on the loaded record — no extra
                    // query, so the list stays free of N+1 (constitution 2.5).
                    ->state(fn (Order $record): string => static::pendingActionReason($record)['label'])
                    ->color(fn (Order $record): string => static::pendingActionReason($record)['color']),

                TextColumn::make('grand_total')
                    ->label('الإجمالي')
                    ->money('EGP')
                    ->visible(static::canSeeFinancials()),

                TextColumn::make('created_at')
                    ->label('منذ')
                    ->since()
                    ->tooltip(fn (Order $record): string => $record->created_at?->format('Y-m-d H:i') ?? '—'),
            ])
            ->recordUrl(
                fn (Order $record): ?string => OrderResource::canView($record)
                    ? OrderResource::getUrl('view', ['record' => $record])
                    : null
            )
            ->emptyStateIcon('heroicon-o-check-circle')
            ->emptyStateHeading('لا شيء ينتظر إجراءً')
            ->emptyStateDescription('كل إثباتات الدفع مُراجَعة، ولا طلبات معلّقة بلا تأكيد.');
    }

    /**
     * Re-reads the cached ids as models.
     *
     * The scan that decides WHICH orders need attention is cached for five
     * minutes (ResolvesPendingActions::pendingActionIds). What runs per render is
     * a primary-key lookup of at most eight ids — the cheapest query MySQL can
     * serve, and unavoidable because Filament's table layer demands a live
     * Builder rather than a materialised collection.
     */
    protected function pendingActionsTableQuery(): Builder
    {
        $ids = static::pendingActionIds(self::PENDING_PREVIEW_LIMIT);

        $query = Order::query()
            ->whereKey($ids)
            ->orderBy('created_at');

        // Only fetch the money column for roles allowed to see money.
        return static::canSeeFinancials()
            ? $query
            : $query->select(['id', 'order_number', 'customer_name', 'status', 'payment_status', 'created_at']);
    }

    /**
     * Low stock as a counted link. Hidden entirely from roles without
     * «products.view», and the counting query is skipped for them too.
     *
     * @return array<int, Tables\Actions\Action>
     */
    protected function lowStockHeaderActions(): array
    {
        if (! BookResource::canViewAny()) {
            return [];
        }

        $count = static::lowStockCount();

        if ($count === 0) {
            return [];
        }

        return [
            Tables\Actions\Action::make('lowStock')
                // Arabic number-noun agreement: «1 كتب» / «3 كتاب» would both be
                // wrong, so the singular is spelled out and the plural puts the
                // numeral last, which stays correct at any count.
                ->label($count === 1
                    ? 'كتاب واحد قارب على النفاد'
                    : "كتب قاربت على النفاد: {$count}")
                ->icon('heroicon-m-exclamation-triangle')
                ->color('warning')
                ->url(BookResource::getUrl('index')),
        ];
    }

    private static function canSeeFinancials(): bool
    {
        return auth()->user()?->can('orders.view_financials') ?? false;
    }
}
