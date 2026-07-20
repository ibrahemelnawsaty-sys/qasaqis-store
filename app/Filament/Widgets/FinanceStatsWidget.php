<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * مؤشرات القسم المالي (المرحلة ١ — الإيراد فقط). يقرأ نطاق التاريخ من فلاتر
 * الصفحة (InteractsWithPageFilters) ويعرض ملخّص FinanceReportService.
 *
 * الأمان (الدستور 4.4): الويدجت يفوّض إذنه مستقلًّا عبر canView — حماية الصفحة
 * وحدها لا تكفي، إذ يمكن استدعاء الويدجت في سياق آخر. أي ويدجت مالي بلا هذا
 * الفحص يسرّب الأرقام.
 */
class FinanceStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    protected function getStats(): array
    {
        [$from, $to] = $this->resolveRange();
        $service = app(FinanceReportService::class);
        $s = $service->summary($from, $to);
        $p = $service->profit($from, $to);       // المرحلة ٢
        $sh = $service->shipping($from, $to);     // المرحلة ٣

        $egp = static fn (?string $v): string => $v === null ? 'غير متاح'
            : number_format((float) $v, 2).' ج.م';
        $pct = static fn (?string $v): string => $v === null ? 'غير متاح'
            : number_format((float) $v, 1).'٪';

        // تنبيه صدق: طلبات محقّقة بلا تكلفة مُدخلة تُستبعد من الربح.
        $costNote = $p['orders_unknown_cost'] > 0
            ? ' · '.number_format($p['orders_unknown_cost']).' طلب بلا تكلفة محفوظة (مستبعد)'
            : '';
        // لا نؤكّد «كل الطلبات لها تكلفة شحن» حين لا طلبات أصلًا (تأكيد كاذب — 1.4).
        $carrierNote = match (true) {
            $sh['orders_carrier_unknown'] > 0 => number_format($sh['orders_carrier_unknown']).' طلب بلا تكلفة شحن مُدخلة',
            $sh['orders_carrier_known'] > 0 => 'كل الطلبات لها تكلفة شحن',
            default => 'لا طلبات محقّقة في هذا المدى',
        };

        return [
            Stat::make('صافي المبيعات', $egp($s['net_sales']))
                ->description('إجمالي '.$egp($s['gross_revenue']).' − خصومات '.$egp($s['discounts']))
                ->color('success'),

            Stat::make('عدد الطلبات', number_format($s['orders']))
                ->description(number_format($s['units']).' وحدة مباعة')
                ->color('primary'),

            Stat::make('متوسط قيمة الطلب', $s['aov'] === null ? 'غير محدد' : $egp($s['aov']))
                ->description('صافي المبيعات ÷ الطلبات')
                ->color('gray'),

            // ===== المرحلة ٢: التكلفة والربح =====
            Stat::make('تكلفة البضاعة (COGS)', $egp($p['cogs']))
                ->description('على '.number_format($p['orders_costed']).' طلب معروف التكلفة'.$costNote)
                ->color('gray'),

            Stat::make('مجمل الربح', $egp($p['gross_profit']))
                ->description('صافي − تكلفة، على الطلبات معروفة التكلفة فقط')
                ->color($p['gross_profit'] === null ? 'gray' : 'success'),

            Stat::make('هامش الربح', $pct($p['margin_pct']))
                ->description('مجمل الربح ÷ صافي المبيعات معروف التكلفة')
                ->color('primary'),

            // ===== المرحلة ٣: الشحن والمساهمة =====
            Stat::make('هامش الشحن', $egp($sh['shipping_margin']))
                ->description('المحصَّل − المدفوع للشركة · '.$carrierNote)
                ->color($sh['shipping_margin'] === null ? 'gray'
                    : ((float) $sh['shipping_margin'] < 0 ? 'danger' : 'success')),

            Stat::make('ربح المساهمة', $egp($sh['contribution']))
                ->description('صافي − تكلفة − شحن، على '.number_format($sh['orders_contribution']).' طلب مكتمل البيانات')
                ->color($sh['contribution'] === null ? 'gray' : 'success'),

            Stat::make('قيد التحصيل', $egp($s['pipeline']))
                ->description('طلبات مؤكّدة لم تُحقَّق بعد — خارج الإيراد')
                ->color('warning'),
        ];
    }

    /**
     * نطاق التاريخ من فلاتر الصفحة، مع افتراض آخر ٣٠ يومًا حين لا فلتر.
     *
     * @return array{0:CarbonImmutable, 1:CarbonImmutable}
     */
    private function resolveRange(): array
    {
        return FinanceRange::fromFilters($this->filters);
    }
}
