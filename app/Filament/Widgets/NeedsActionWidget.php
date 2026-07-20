<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Book;
use App\Models\Order;
use App\Models\PaymentProof;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * القسم ٢ — «تحتاج إجراءً الآن» (المرحلة ١ MVP). طابور العمل اليومي: كل عدّاد لقطة
 * لحظية من عمود حقيقي، ويصل بالنقر إلى قائمة الطلبات مُرشَّحة بنفس الحالة. اللون
 * يشتدّ (danger/warning) عند وجود ما يحتاج تدخّلًا فيلفت النظر.
 *
 * محجوب خلف orders.view (الدستور 4.4). العتبات (مخزون ≤ 3) قيم بدء معقولة تُنقل
 * لاحقًا إلى إعدادات قابلة للضبط (المرحلة ٠ الموسّعة).
 */
class NeedsActionWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view');
    }

    protected function getStats(): array
    {
        $ordersFiltered = static fn (string $filter, string $value): string => OrderResource::getUrl('index', [
            'tableFilters' => [$filter => ['value' => $value]],
        ]);

        // pending بلا تأكيد واتساب — أعلى أولوية (كل ساعة تأخير ترفع خطر الإلغاء).
        $awaitingConfirm = Order::query()
            ->where('status', 'pending')
            ->whereNull('whatsapp_confirmed_at')
            ->count();

        // إثباتات دفع تنتظر مراجعتك (العميل حوّل فعلًا).
        $proofs = PaymentProof::query()->where('review_status', 'pending_review')->count();

        // مؤكّد/قيد التجهيز بلا رقم تتبّع (جاهز ولم يُسلَّم لشركة الشحن).
        $noTracking = Order::query()
            ->whereIn('status', ['confirmed', 'processing'])
            ->whereNull('tracking_number')
            ->count();

        // مشحون لم يُسلَّم بعد (متابعة الشحنات الجارية).
        $shipped = Order::query()->where('status', 'shipped')->count();

        // دفع يدوي (غير COD) لم يكتمل.
        $manualPending = Order::query()
            ->where('payment_method', '!=', 'cod')
            ->whereIn('payment_status', ['unpaid', 'pending_review'])
            ->count();

        // مخزون منخفض/نافد لكتب منشورة تُدار مخزونيًّا.
        $lowStock = Book::query()
            ->where('is_published', true)
            ->where('manage_stock', true)
            ->where(fn ($q) => $q
                ->where('stock_status', 'out_of_stock')
                ->orWhere('stock_quantity', '<=', 3))
            ->count();

        return [
            Stat::make('تنتظر تأكيد واتساب', $awaitingConfirm)
                ->description('طلبات pending بلا تأكيد')
                ->descriptionIcon('heroicon-m-phone')
                ->color($awaitingConfirm > 0 ? 'danger' : 'gray')
                ->url($ordersFiltered('status', 'pending')),

            Stat::make('إثباتات تنتظر المراجعة', $proofs)
                ->description('العميل حوّل وينتظر اعتمادك')
                ->descriptionIcon('heroicon-m-document-check')
                ->color($proofs > 0 ? 'warning' : 'gray')
                ->url(OrderResource::getUrl('index', [
                    'tableFilters' => ['payment_status' => ['value' => 'pending_review']],
                ])),

            Stat::make('مؤكّد بلا رقم تتبّع', $noTracking)
                ->description('جاهز ولم يُسلَّم لشركة الشحن')
                ->descriptionIcon('heroicon-m-cube')
                ->color($noTracking > 0 ? 'warning' : 'gray')
                ->url($ordersFiltered('status', 'confirmed')),

            Stat::make('مشحون ولم يُسلَّم', $shipped)
                ->description('متابعة الشحنات الجارية')
                ->descriptionIcon('heroicon-m-truck')
                ->color('primary')
                ->url($ordersFiltered('status', 'shipped')),

            Stat::make('دفع يدوي معلّق', $manualPending)
                ->description('تحويلات لم تُؤكَّد بعد')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color($manualPending > 0 ? 'warning' : 'gray'),

            Stat::make('مخزون منخفض/نافد', $lowStock)
                ->description('كتب منشورة تحتاج تعبئة')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStock > 0 ? 'danger' : 'gray'),
        ];
    }
}
