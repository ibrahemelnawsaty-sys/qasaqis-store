<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Widgets\Concerns\CachesDashboardData;
use App\Filament\Widgets\Concerns\ScopesRevenue;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentProof;
use Filament\Pages\Dashboard;

/**
 * لوحة العمليات وذكاء المنتجات — لوحة الأدمن الافتراضية (تحلّ محلّ لوحة الودجت).
 * تمتدّ من Dashboard فتُخدَم على جذر اللوحة (/admin)، وتعرض التصميم المعتمَد كاملًا
 * ببيانات حقيقية (قُمع، دونات، بطاقات ملوّنة، توصيات) عبر قالبها المخصّص.
 *
 * كل رقم مُشتقّ من عمود حقيقي. اللقطات اللحظية تعمل من أوّل طلب؛ المتوسّطات تُعرَض
 * مع عدد العيّنة n بشفافية. الماليات (إيراد/هامش) محجوبة خلف orders.view_financials.
 * التخزين المؤقّت والطلب-الصالح من حُقن اللوحة المشتركة اتّساقًا.
 *
 * الأداء: getViewData يُحسب مرّة لكل تحميل صفحة، والحسابات المشتركة (queues/coverage/
 * governorates/funnel) تُمرَّر إلى timing()/recommendations() بدل إعادة استعلامها.
 * «المتصفّحون الآن» عُزل في مكوّن Livewire مستقلّ يستطلع نفسه، فلا تُعاد الصفحة كلّها
 * دوريًّا (أُزيل wire:poll عن الحاوية).
 */
class OpsDashboard extends Dashboard
{
    use CachesDashboardData;
    use ScopesRevenue;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'لوحة العمليات';

    protected static string $view = 'filament.pages.ops-dashboard';

    /**
     * لا نرسم ودجت Filament المكتشَفة على هذه اللوحة — قالبنا المخصّص هو المحتوى.
     * (ودجت الجلسة الأخرى تبقى موجودة كأصناف لكنها لا تُعرَض هنا.)
     *
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return 'لوحة العمليات وذكاء المنتجات';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $canFin = (bool) auth()->user()?->can('orders.view_financials');
        $since = static::trendWindowStart();

        // نحسب هذه مرّة واحدة ونمرّرها، فلا تعيد recommendations()/timing() استدعاءها
        // (كان تكرارًا يضرب كاش قاعدة البيانات عدّة مرّات في نفس الطلب).
        $queues = $this->queues();
        $coverage = $this->coverage();
        $governorates = $this->governorates($since);
        $funnel = $this->funnel($since);

        // «المتصفّحون الآن» لم يعد هنا — عُزل في مكوّن Livewire صغير يستطلع نفسه،
        // فلا تُعاد الصفحة كلها كل 30 ثانية (المكسب الأكبر).
        return [
            'canFin' => $canFin,
            'overview' => $this->overview($canFin, $since),
            'queues' => $queues,
            'funnel' => $funnel,
            'payments' => $this->payments($since),
            'governorates' => $governorates,
            'timing' => $this->timing($funnel),
            'topBooks' => $this->topBooks($since),
            'coverage' => $coverage,
            'categories' => $this->categories($since, $canFin),
            'monthly' => $this->monthly(),
            'recommendations' => $this->recommendations($queues, $coverage, $governorates),
        ];
    }

    private function overview(bool $canFin, $since): array
    {
        return static::rememberDashboard('page.overview.'.($canFin ? '1' : '0'), function () use ($canFin, $since): array {
            $data = [
                'today' => Order::whereDate('created_at', today())->count(),
                'week' => Order::where('created_at', '>=', now()->subDays(7))->count(),
                'month' => Order::where('created_at', '>=', $since)->count(),
                'first_order' => optional(Order::min('created_at')),
                'total_orders' => Order::count(),
            ];

            if ($canFin) {
                // عدّ ومجموع في استعلام واحد بدل clone×2 على نفس المرشِّح.
                $r = Order::query()
                    ->whereIn('status', ['delivered', 'completed'])
                    ->where('created_at', '>=', $since)
                    ->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total), 0) as rev')
                    ->first();
                $n = (int) ($r->n ?? 0);
                $rev = (float) ($r->rev ?? 0);
                $data['revenue'] = $rev;
                $data['aov'] = $n > 0 ? $rev / $n : 0.0;
                $data['realized_n'] = $n;
            }

            return $data;
        }, []);
    }

    private function queues(): array
    {
        return static::rememberDashboard('page.queues', function (): array {
            // التحويل اليدوي: عدّ ومجموع في استعلام واحد (نفس المرشِّح كان يُنفَّذ مرّتين).
            $manual = Order::where('payment_method', '!=', 'cod')
                ->whereIn('payment_status', ['unpaid', 'pending_review'])
                ->selectRaw('COUNT(*) as n, COALESCE(SUM(grand_total), 0) as s')
                ->first();

            return [
                'confirm' => Order::where('status', 'pending')->whereNull('whatsapp_confirmed_at')->count(),
                'proofs' => PaymentProof::where('review_status', 'pending_review')->count(),
                'no_tracking' => Order::whereIn('status', ['confirmed', 'processing'])->whereNull('tracking_number')->count(),
                'shipped' => Order::where('status', 'shipped')->count(),
                'manual_n' => (int) ($manual->n ?? 0),
                'manual_sum' => (float) ($manual->s ?? 0),
                'low_stock' => Book::where('is_published', true)->where('manage_stock', true)
                    ->where(fn ($q) => $q->where('stock_status', 'out_of_stock')->orWhere('stock_quantity', '<=', 5))->count(),
            ];
        }, []);
    }

    private function funnel($since): array
    {
        return static::rememberDashboard('page.funnel', function () use ($since): array {
            $counts = Order::where('created_at', '>=', $since)
                ->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
            $g = fn (array $ks): int => (int) collect($ks)->sum(fn ($k) => (int) ($counts[$k] ?? 0));

            return [
                'total' => (int) $counts->sum(),
                'confirmed' => $g(['confirmed', 'processing', 'shipped', 'delivered', 'completed']),
                'processing' => $g(['processing', 'shipped', 'delivered', 'completed']),
                'shipped' => $g(['shipped', 'delivered', 'completed']),
                'delivered' => $g(['delivered', 'completed']),
                'lost' => $g(['cancelled', 'refused']),
            ];
        }, []);
    }

    private function payments($since): array
    {
        return static::rememberDashboard('page.payments', fn (): array => Order::where('created_at', '>=', $since)
            ->selectRaw('payment_method, COUNT(*) as n')->groupBy('payment_method')
            ->orderByDesc('n')->pluck('n', 'payment_method')->all(), []);
    }

    private function governorates($since): array
    {
        return static::rememberDashboard('page.governorates', function () use ($since): array {
            return Order::where('created_at', '>=', $since)
                ->selectRaw('governorate,
                    COUNT(*) as orders,
                    SUM(grand_total) as revenue,
                    SUM(CASE WHEN status IN ("cancelled","refused") THEN 1 ELSE 0 END) as lost')
                ->groupBy('governorate')->orderByDesc('orders')->limit(6)->get()
                ->map(fn ($r): array => [
                    'name' => $r->governorate,
                    'orders' => (int) $r->orders,
                    'revenue' => (float) $r->revenue,
                    'lost_pct' => $r->orders > 0 ? round(((int) $r->lost / (int) $r->orders) * 100) : 0,
                ])->all();
        }, []);
    }

    /**
     * @param  array  $funnel  مخرجات funnel() — نشتقّ منها الإلغاء بدل استعلامَي COUNT إضافيَّين.
     */
    private function timing(array $funnel): array
    {
        $since = static::trendWindowStart();

        return static::rememberDashboard('page.timing', function () use ($funnel, $since): array {
            // زمن التأكيد الدقيق من عمود حقيقي (مقيَّد بنافذة الاتجاه ليستفيد من الفهرس).
            $confirm = Order::whereNotNull('whatsapp_confirmed_at')
                ->where('created_at', '>=', $since)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, whatsapp_confirmed_at)) as m, COUNT(*) as n')->first();
            // زمن مراجعة الإثبات (نفس النافذة).
            $proof = PaymentProof::whereNotNull('reviewed_at')
                ->where('created_at', '>=', $since)
                ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, reviewed_at)) as m, COUNT(*) as n')->first();

            // معدّل الإلغاء/الرفض — مشتقّ من funnel() المحسوب مسبقًا (نفس النافذة)، بلا استعلام جديد.
            $total = (int) ($funnel['total'] ?? 0);
            $lost = (int) ($funnel['lost'] ?? 0);

            return [
                'confirm_min' => $confirm?->m, 'confirm_n' => (int) ($confirm?->n ?? 0),
                'proof_min' => $proof?->m, 'proof_n' => (int) ($proof?->n ?? 0),
                'cancel_pct' => $total > 0 ? round(($lost / $total) * 100) : 0,
                'cancel_total' => $total, 'cancel_lost' => $lost,
            ];
        }, []);
    }

    private function topBooks($since): array
    {
        return static::rememberDashboard('page.topbooks', function () use ($since): array {
            return static::scopeRevenueOrders(
                OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                'orders.status',
            )
                ->join('books', 'books.id', '=', 'order_items.book_id')
                ->where('orders.created_at', '>=', $since)
                ->groupBy('order_items.book_id', 'books.title')
                ->selectRaw('books.title, SUM(order_items.quantity) as qty, SUM(order_items.line_total) as revenue')
                ->orderByDesc('qty')->limit(6)->get()
                ->map(fn ($r): array => ['title' => $r->title, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue])->all();
        }, []);
    }

    private function coverage(): array
    {
        return static::rememberDashboard('page.coverage', function (): array {
            $sold = static::scopeRevenueOrders(
                OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                'orders.status',
            )->where('orders.created_at', '>=', static::trendWindowStart())
                ->groupBy('order_items.book_id')
                ->selectRaw('order_items.book_id, SUM(order_items.quantity) as qty')
                ->pluck('qty', 'order_items.book_id');

            $days = max(1, self::TREND_DAYS);

            return Book::where('is_published', true)->where('manage_stock', true)
                ->get(['id', 'title', 'stock_quantity', 'stock_status'])
                ->map(function (Book $b) use ($sold, $days): array {
                    $qty = (int) ($sold[$b->id] ?? 0);
                    $vel = $qty / $days;
                    $cover = $vel > 0 ? round($b->stock_quantity / $vel) : null;

                    return [
                        'title' => $b->title, 'stock' => (int) $b->stock_quantity, 'sold' => $qty,
                        'cover' => $cover, 'out' => $b->stock_status === 'out_of_stock' || $b->stock_quantity <= 0,
                    ];
                })->sortBy(fn (array $r): float => $r['out'] ? -1 : ($r['cover'] ?? 1e9))
                ->take(6)->values()->all();
        }, []);
    }

    private function categories($since, bool $canFin): array
    {
        return static::rememberDashboard('page.categories', function () use ($since): array {
            return static::scopeRevenueOrders(
                OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id'),
                'orders.status',
            )->join('books', 'books.id', '=', 'order_items.book_id')
                ->leftJoin('categories', 'categories.id', '=', 'books.category_id')
                ->where('orders.created_at', '>=', $since)
                ->groupBy('categories.id', 'categories.name')
                ->selectRaw("COALESCE(categories.name, 'بلا قسم') as name, SUM(order_items.quantity) as qty, SUM(order_items.line_total) as revenue")
                ->orderByDesc('revenue')->limit(6)->get()
                ->map(fn ($r): array => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue])->all();
        }, []);
    }

    private function monthly(): array
    {
        return static::rememberDashboard('page.monthly', fn (): array => static::scopeRevenueOrders(Order::query())
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as orders")
            ->groupBy('ym')->orderBy('ym')->get()
            ->map(fn ($r): array => ['ym' => $r->ym, 'orders' => (int) $r->orders])->all(), []);
    }

    /**
     * توصيات قائمة على قواعد من الأرقام المحسوبة (عمود حقيقي لكل شرط).
     * تُمرَّر القيم المحسوبة مسبقًا من getViewData()، فلا تُعاد الاستعلامات نفسها.
     */
    private function recommendations(array $q, array $cov, array $gov): array
    {
        $recs = [];

        foreach ($cov as $c) {
            if ($c['out'] && $c['sold'] > 0) {
                $recs[] = ['p' => 'urgent', 'icon' => '⏰', 'msg' => 'كتاب مطلوب نفد: «'.$c['title'].'» (بيع '.$c['sold'].' آخر 30ي) — أصدر أمر توريد الآن.'];
            } elseif ($c['cover'] !== null && $c['cover'] <= 7) {
                $recs[] = ['p' => 'urgent', 'icon' => '📉', 'msg' => '«'.$c['title'].'» يكفي '.$c['cover'].' أيّام فقط — أعد التعبئة قبل النفاد.'];
            }
        }
        if ($q['confirm'] > 0) {
            $recs[] = ['p' => 'urgent', 'icon' => '📞', 'msg' => $q['confirm'].' طلب ينتظر تأكيد واتساب — راسل العملاء الآن أو ألغِ لتحرير المخزون.'];
        }
        if ($q['proofs'] > 0) {
            $recs[] = ['p' => 'urgent', 'icon' => '🧾', 'msg' => $q['proofs'].' إثبات دفع ينتظر مراجعتك — العميل حوّل فعلًا.'];
        }
        if ($q['no_tracking'] > 0) {
            $recs[] = ['p' => 'important', 'icon' => '📦', 'msg' => $q['no_tracking'].' طلب مؤكّد بلا رقم تتبّع — جهّز الشحنة وأدخل التتبّع.'];
        }
        if ($q['manual_n'] > 0) {
            $recs[] = ['p' => 'important', 'icon' => '💵', 'msg' => $q['manual_n'].' تحويل يدوي معلّق ('.number_format($q['manual_sum']).' ج.م) — ذكّر العملاء برفع الإثبات.'];
        }
        foreach ($gov as $g) {
            if ($g['orders'] >= 5 && $g['lost_pct'] >= 25) {
                $recs[] = ['p' => 'important', 'icon' => '🗺️', 'msg' => 'مرتجعات مرتفعة في '.$g['name'].' ('.$g['lost_pct'].'%) — اشترط دفعًا مسبقًا لتلك المحافظة.'];
            }
        }

        return array_slice($recs, 0, 8);
    }
}
