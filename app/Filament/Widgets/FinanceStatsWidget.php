<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\PaymentMethod;
use App\Services\Finance\FinanceReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * لوحة المؤشّرات المالية — تُصيَّر كقصّة مقروءة لا كجدار بطاقات: ثلاثة مؤشّرات
 * «نجم» في الأعلى، ثم نطاقات مجمّعة (إيراد / تكلفة وربح / شحن ومساهمة)، ثم جسر
 * يوضّح كيف تتحوّل المساهمة إلى صافي ربح، ثم تقسيم الإيراد حسب طريقة الدفع.
 *
 * صدق السكان (الدستور 1.4): كل نطاق يحمل شارة تُبيّن على أي مجموعة طلبات حُسب —
 * فلا نخلط إيراد كل الطلبات بتكلفة مجموعة فرعية. لهذا لا يوجد «شلال» يطرح COGS
 * من إجمالي الإيراد؛ الجسر يبدأ من المساهمة (سكان مكتمل البيانات) وحده.
 *
 * الأمان (4.4): canView يفوّض الإذن مستقلًّا — حماية الصفحة وحدها لا تكفي.
 * كل التنسيق (أرقام، نِسب، عرض الأشرطة) يُحسب هنا في PHP ويصل للقالب جاهزًا.
 */
class FinanceStatsWidget extends Widget
{
    use InteractsWithPageFilters;

    protected static string $view = 'filament.widgets.finance-overview';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('orders.view_financials');
    }

    /**
     * نموذج العرض الكامل للوحة المؤشّرات — كل الأرقام منسّقة نصًّا جاهزة للعرض.
     *
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        [$from, $to] = FinanceRange::fromFilters($this->filters);
        $service = app(FinanceReportService::class);

        $s = $service->summary($from, $to);       // م١ — الإيراد
        $p = $service->profit($from, $to);         // م٢ — التكلفة والربح
        $sh = $service->shipping($from, $to);      // م٣ — الشحن والمساهمة
        $np = $service->netProfit($from, $to);     // م٤ — صافي ربح النشاط

        return [
            'hero' => [
                $this->m('صافي ربح النشاط', $np['net_profit'], [
                    'tone' => $this->sign($np['net_profit']),
                    'sub' => 'المساهمة − مرتجعات − رسوم − مصروفات',
                ]),
                $this->m('الإيراد المحقّق', $s['net_sales'], [
                    'tone' => 'accent',
                    'sub' => number_format($s['orders']).' طلب · '.number_format($s['units']).' وحدة',
                ]),
                $this->m('هامش الربح', $p['margin_pct'], [
                    'unit' => '٪',
                    // sign() لا 'pos' الثابت: الهامش قد يكون سالبًا (COGS > صافي)،
                    // فيجب أن يظهر أحمر لا أخضر كي لا يكذّب اللونُ الإشارةَ.
                    'tone' => $this->sign($p['margin_pct']),
                    'sub' => 'مجمل الربح ÷ صافي المبيعات معروف التكلفة',
                ]),
            ],

            'bands' => [
                [
                    'title' => 'الإيراد المحقّق',
                    'badge' => 'على كل الطلبات المحقّقة في المدى',
                    'badge_tone' => 'neutral',
                    'cards' => [
                        $this->m('إجمالي المبيعات', $s['gross_revenue']),
                        $this->m('الخصومات', $s['discounts'], ['tone' => (float) $s['discounts'] > 0 ? 'neg' : 'neutral']),
                        $this->m('صافي المبيعات', $s['net_sales'], ['tone' => 'pos']),
                        $this->m('الشحن المحصَّل', $s['shipping']),
                        $this->m('عدد الطلبات', (string) $s['orders'], ['unit' => '', 'tone' => 'accent']),
                        $this->m('الوحدات المباعة', (string) $s['units'], ['unit' => '']),
                        $this->m('متوسط قيمة الطلب', $s['aov'], ['sub' => 'صافي ÷ عدد الطلبات']),
                    ],
                ],
                [
                    'title' => 'التكلفة والربح',
                    'badge' => $p['orders_unknown_cost'] > 0
                        ? number_format($p['orders_unknown_cost']).' طلب بلا تكلفة محفوظة — مستبعد من الحساب'
                        : 'على '.number_format($p['orders_costed']).' طلب معروف التكلفة بالكامل',
                    'badge_tone' => $p['orders_unknown_cost'] > 0 ? 'warn' : 'neutral',
                    'cards' => [
                        $this->m('تكلفة البضاعة (COGS)', $p['cogs'], ['tone' => (float) $p['cogs'] > 0 ? 'neg' : 'neutral']),
                        $this->m('مجمل الربح', $p['gross_profit'], ['tone' => $this->sign($p['gross_profit'])]),
                        $this->m('هامش الربح', $p['margin_pct'], ['unit' => '٪', 'tone' => $this->sign($p['margin_pct'])]),
                    ],
                ],
                [
                    'title' => 'الشحن والمساهمة',
                    'badge' => match (true) {
                        $sh['orders_carrier_unknown'] > 0 => number_format($sh['orders_carrier_unknown']).' طلب بلا تكلفة شحن مُدخلة',
                        $sh['orders_carrier_known'] > 0 => 'كل الطلبات المحقّقة لها تكلفة شحن',
                        default => 'لا طلبات محقّقة في هذا المدى',
                    },
                    'badge_tone' => $sh['orders_carrier_unknown'] > 0 ? 'warn' : 'neutral',
                    'cards' => [
                        $this->m('تكلفة شركة الشحن', $sh['carrier_cost'], ['tone' => (float) $sh['carrier_cost'] > 0 ? 'neg' : 'neutral']),
                        $this->m('هامش الشحن', $sh['shipping_margin'], ['tone' => $this->sign($sh['shipping_margin'])]),
                        $this->m('ربح المساهمة', $sh['contribution'], [
                            'tone' => $this->sign($sh['contribution']),
                            'sub' => 'على '.number_format($sh['orders_contribution']).' طلب مكتمل البيانات',
                        ]),
                    ],
                ],
            ],

            'bridge' => $this->bridge($sh, $np),
            'breakdown' => $this->breakdown($service, $from, $to, $s['net_sales']),
            // قيد التحصيل خارج نطاق الإيراد المحقّق (سكان مغاير: طلبات لم تُحقَّق
            // بعد)، فيُعرض بطاقةً مستقلّة لا ضمن نطاق يدّعي «كل الطلبات المحقّقة».
            'pipeline' => $this->m('قيد التحصيل', $s['pipeline'], ['tone' => 'warn']),
        ];
    }

    /**
     * جسر الربح: من المساهمة إلى صافي الربح على سكان واحد (مكتمل البيانات). يُعرض
     * فقط حين تتوفّر المساهمة — وإلا فلا رقم مبنيّ على مجهول (1.4). عرض كل شريط
     * نسبةً إلى مرجع ثابت (الأكبر بين |المساهمة| ومجموع الخصومات) ليقرأ المالك
     * حجم كل خصم بصريًّا، ويبقى ذا معنى حتى في فترة الخسارة (مساهمة سالبة).
     *
     * @param  array<string, mixed>  $sh
     * @param  array<string, mixed>  $np
     * @return array<string, mixed>|null
     */
    private function bridge(array $sh, array $np): ?array
    {
        $contribution = $sh['contribution'];
        if ($contribution === null) {
            return null;
        }

        $refunds = (float) $np['refunds'];
        $fees = (float) $np['fees'];
        $expenses = (float) $np['expenses'];
        $net = (float) ($np['net_profit'] ?? '0');

        // مرجع عرض الأشرطة: الأكبر بين |المساهمة| ومجموع الخصومات. لا نقتصر على
        // المساهمة وحدها: حين تكون سالبة (فترة خسارة) كان max(contribution,0.01)
        // يُسقط المرجع إلى 0.01 فتمتلئ كل الأشرطة 100٪ وتُشوَّه النِسب. هذا المرجع
        // يبقى ذا معنى في الحالتين، والقسمة عليه آمنة (>= 0.01).
        $scale = max(abs((float) $contribution), $refunds + $fees + $expenses, 0.01);
        $pct = fn (float $v): float => min(100.0, abs($v) / $scale * 100);

        return [
            'contribution' => $this->m('ربح المساهمة', $contribution, ['tone' => $this->sign($contribution)]),
            'contribution_width' => $pct((float) $contribution),
            'steps' => [
                ['label' => 'المرتجعات', 'value' => $np['refunds'], 'width' => $pct($refunds)],
                ['label' => 'رسوم المعالجة', 'value' => $np['fees'], 'width' => $pct($fees)],
                ['label' => 'المصروفات', 'value' => $np['expenses'], 'width' => $pct($expenses), 'note' => 'على مستوى الفترة'],
            ],
            'net' => $this->m('صافي ربح النشاط', $np['net_profit'], ['tone' => $this->sign($np['net_profit'])]),
            'net_width' => $pct($net),
            'net_negative' => $np['net_profit'] !== null && $net < 0,
        ];
    }

    /**
     * تقسيم الإيراد المحقّق حسب طريقة الدفع. التسمية من جدول PaymentMethod
     * (code→name) لا مخترعة؛ ما لا اسم له يظهر برمزه أو «غير محدّد».
     *
     * @return array<string, mixed>
     */
    private function breakdown(FinanceReportService $service, CarbonImmutable $from, CarbonImmutable $to, string $totalNet): array
    {
        $rows = $service->breakdownBy('payment_method', $from, $to);

        $labels = rescue(
            fn (): array => PaymentMethod::query()->pluck('name', 'code')->all(),
            [],
            report: false,
        );

        $total = max((float) $totalNet, 0.01);

        return [
            'rows' => $rows->map(fn (array $r): array => [
                'label' => $labels[$r['key']] ?? ($r['key'] ?? 'غير محدّد'),
                'orders' => number_format($r['orders']),
                'net' => number_format((float) $r['net_sales'], 2),
                'width' => min(100.0, (float) $r['net_sales'] / $total * 100),
            ])->all(),
        ];
    }

    /**
     * يبني بطاقة مقياس منسّقة. القيمة null تعني «غير متاح» (لا صفر مضلِّل — 1.4).
     *
     * @param  array<string, mixed>  $o
     * @return array<string, mixed>
     */
    private function m(string $label, ?string $value, array $o = []): array
    {
        $unit = $o['unit'] ?? 'ج.م';
        $dec = $unit === '٪' ? 1 : ($unit === '' ? 0 : 2);

        return [
            'label' => $label,
            'na' => $value === null,
            'num' => $value === null ? null : number_format((float) $value, $dec),
            'unit' => $unit,
            'tone' => $o['tone'] ?? 'neutral',
            'sub' => $o['sub'] ?? '',
        ];
    }

    /** إشارة القيمة لتلوينها: غير متاح→محايد، سالب→أحمر، وإلا→أخضر. */
    private function sign(?string $value): string
    {
        return $value === null ? 'neutral' : ((float) $value < 0 ? 'neg' : 'pos');
    }
}
