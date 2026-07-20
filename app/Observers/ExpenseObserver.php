<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Expense;
use App\Services\Finance\FinanceReportService;

/**
 * إبطال كاش تقارير القسم المالي عند أي تغيّر في المصروفات (م٤ج).
 *
 * المصروفات مصدر مستقل عن الطلبات، فلا يمرّ حفظها بـ OrderObserver الذي يبطل
 * الكاش. بدون هذا المُراقب يظل «صافي ربح النشاط» و«المصروفات» على الداشبورد
 * قديمًا حتى انتهاء عمر الكاش بعد إضافة/تعديل/حذف مصروف (الدستور 5.4).
 */
class ExpenseObserver
{
    public function saved(Expense $expense): void
    {
        app(FinanceReportService::class)->flush();
    }

    public function deleted(Expense $expense): void
    {
        app(FinanceReportService::class)->flush();
    }
}
