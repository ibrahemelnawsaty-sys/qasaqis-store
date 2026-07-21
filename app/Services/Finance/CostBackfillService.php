<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\OrderItem;
use App\Support\Money;

/**
 * ترحيل تكلفة لمرّة واحدة للطلبات القديمة: يملأ unit_cost/line_cost للسطور التي
 * أُنشئت قبل تفعيل تتبّع التكلفة (unit_cost = NULL)، عبر BookCostResolver — أي
 * تكلفة مُدخَلة يدويًا إن وُجدت، وإلا تقدير من خصم دار النشر (السعر × (١ − النسبة)).
 *
 * قيود السلامة (الدستور 1.1/1.4/2.3/3.5):
 *   - مصدر واحد للتكلفة (BookCostResolver) يطابق لقطة البيع تمامًا (bcmath).
 *   - idempotent: لا يلمس سطرًا له تكلفة محفوظة (whereNull فقط) — فإعادة تشغيله
 *     آمنة ولا تُفسد لقطة أصلية.
 *   - يُعلّم السطور المشتقّة من الخصم بـ cost_is_estimated=true تمييزًا لها.
 *   - كتاب محذوف صلبًا (book_id صار NULL) أو بلا سعر بيع: لا سبيل لتكلفته فيُترك.
 *   - كتاب محذوف ناعمًا: نأخذ تكلفته عبر withTrashed.
 *
 * تنبيه صدق: التقدير يستخدم السعر ونسبة الخصم **الحاليَّين** لا لحظة البيع.
 */
class CostBackfillService
{
    public function __construct(
        private FinanceReportService $finance,
        private BookCostResolver $resolver,
    ) {}

    /**
     * @return array{filled:int, estimated:int, orders:int, skipped:int}
     */
    public function run(bool $dryRun = false): array
    {
        $filled = 0;
        $estimated = 0;
        $skipped = 0;
        $orderIds = [];

        // chunkById آمن مع تعديل العمود المُرشَّح عليه (whereNull): يتقدّم بالمعرّف.
        // withTrashed للكتب المحذوفة ناعمًا، مع publisher لحساب نسبة الخصم (بلا N+1).
        OrderItem::query()
            ->whereNull('unit_cost')
            ->with(['book' => fn ($q) => $q->withTrashed()->with('publisher')])
            ->chunkById(500, function ($items) use (&$filled, &$estimated, &$skipped, &$orderIds, $dryRun): void {
                foreach ($items as $item) {
                    $book = $item->book;

                    if ($book === null) {
                        $skipped++; // كتاب محذوف صلبًا — لا تكلفة له.

                        continue;
                    }

                    $resolved = $this->resolver->resolve($book);

                    if ($resolved['amount'] === null) {
                        $skipped++; // بلا سعر بيع — تعذّر التقدير.

                        continue;
                    }

                    $unitCost = $resolved['amount'];
                    $lineCost = Money::multiplyByQty($unitCost, (int) $item->quantity);

                    if (! $dryRun) {
                        $item->update([
                            'unit_cost' => $unitCost,
                            'line_cost' => $lineCost,
                            'cost_is_estimated' => $resolved['estimated'],
                        ]);
                    }

                    $filled++;
                    if ($resolved['estimated']) {
                        $estimated++;
                    }
                    $orderIds[$item->order_id] = true;
                }
            });

        // إبطال كاش تقارير المالية كي تظهر الأرقام المحدّثة فورًا (لا في التجريبي).
        if (! $dryRun && $filled > 0) {
            $this->finance->flush();
        }

        return [
            'filled' => $filled,
            'estimated' => $estimated,
            'orders' => count($orderIds),
            'skipped' => $skipped,
        ];
    }
}
