<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\OrderItem;
use App\Support\Money;

/**
 * ترحيل تكلفة لمرّة واحدة للطلبات القديمة: يملأ unit_cost/line_cost للسطور التي
 * أُنشئت قبل إدخال سعر الشراء (unit_cost = NULL)، من سعر الشراء **الحالي** للكتاب.
 *
 * لماذا: لقطة التكلفة تُلتقَط لحظة الطلب (PlaceOrderAction)، فالطلبات السابقة
 * لإدخال الأسعار تبقى بلا تكلفة فتُستبعد من الربح. هذه الأداة تسدّ ذلك للبيانات
 * التاريخية بعد تفعيل تتبّع التكلفة.
 *
 * قيود السلامة (الدستور 1.1/1.4/3.5):
 *   - يطابق منطق PlaceOrderAction بالضبط: Money::normalize ثم multiplyByQty
 *     (bcmath لا float).
 *   - idempotent: لا يلمس سطرًا له تكلفة محفوظة (whereNull فقط) — فلا يُفسد لقطة
 *     أصلية دقيقة إن أعيد تشغيله.
 *   - لا يخترع صفرًا: سطر كتابٍ بلا سعر شراء يُترك NULL (يُعدّ في skipped_no_cost).
 *   - كتاب محذوف ناعمًا: نأخذ تكلفته عبر withTrashed. كتاب محذوف صلبًا (book_id
 *     صار NULL) لا سبيل لتكلفته فيُترك (skipped_missing_book).
 *
 * تنبيه صدق: يستخدم السعر **الحالي** لا سعر وقت البيع؛ فإن تغيّرت التكلفة منذ
 * الطلب لم تعُد اللقطة تاريخية بدقّة. مناسب لمتجرٍ يبدأ تتبّع التكلفة لأول مرّة.
 */
class CostBackfillService
{
    public function __construct(private FinanceReportService $finance) {}

    /**
     * @return array{filled:int, orders:int, skipped_no_cost:int, skipped_missing_book:int}
     */
    public function run(bool $dryRun = false): array
    {
        $filled = 0;
        $skippedNoCost = 0;
        $skippedMissingBook = 0;
        $orderIds = [];

        // chunkById آمن مع تعديل العمود المُرشَّح عليه (whereNull): يتقدّم بالمعرّف
        // لا بالإزاحة، فلا يتخطّى ولا يُعيد صفًّا. withTrashed ليأخذ الكتب المحذوفة ناعمًا.
        OrderItem::query()
            ->whereNull('unit_cost')
            ->with(['book' => fn ($q) => $q->withTrashed()])
            ->chunkById(500, function ($items) use (&$filled, &$skippedNoCost, &$skippedMissingBook, &$orderIds, $dryRun): void {
                foreach ($items as $item) {
                    $book = $item->book;

                    if ($book === null) {
                        $skippedMissingBook++;

                        continue;
                    }

                    if ($book->cost_price === null) {
                        $skippedNoCost++;

                        continue;
                    }

                    $unitCost = Money::normalize($book->cost_price);
                    $lineCost = Money::multiplyByQty($unitCost, (int) $item->quantity);

                    if (! $dryRun) {
                        $item->update(['unit_cost' => $unitCost, 'line_cost' => $lineCost]);
                    }

                    $filled++;
                    $orderIds[$item->order_id] = true;
                }
            });

        // إبطال كاش تقارير المالية كي تظهر الأرقام المحدّثة فورًا (لا في التجريبي).
        if (! $dryRun && $filled > 0) {
            $this->finance->flush();
        }

        return [
            'filled' => $filled,
            'orders' => count($orderIds),
            'skipped_no_cost' => $skippedNoCost,
            'skipped_missing_book' => $skippedMissingBook,
        ];
    }
}
