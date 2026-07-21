<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Finance\CostBackfillService;
use Illuminate\Console\Command;

/**
 * ترحيل تكلفة الطلبات القديمة من سطر الأوامر. شغّل --dry-run أولًا لمعاينة ما
 * سيُملأ دون كتابة، ثم بلا الخيار للتنفيذ الفعلي.
 */
class BackfillOrderCosts extends Command
{
    protected $signature = 'finance:backfill-costs {--dry-run : معاينة ما سيُملأ دون أي كتابة}';

    protected $description = 'يملأ تكلفة الطلبات القديمة (unit_cost/line_cost) من سعر الشراء الحالي للكتب';

    public function handle(CostBackfillService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $r = $service->run($dry);

        $prefix = $dry ? '[تجريبي — بلا كتابة] ' : '';
        $this->info($prefix.'سطور مُلئت: '.$r['filled'].' في '.$r['orders'].' طلب.');

        if ($r['skipped_no_cost'] > 0) {
            $this->warn('سطور بلا سعر شراء للكتاب (تُركت NULL): '.$r['skipped_no_cost']);
        }

        if ($r['skipped_missing_book'] > 0) {
            $this->warn('سطور بكتاب محذوف نهائيًا (تعذّر ترحيلها): '.$r['skipped_missing_book']);
        }

        if ($dry && $r['filled'] > 0) {
            $this->line('شغّل الأمر بلا --dry-run للتنفيذ.');
        }

        return self::SUCCESS;
    }
}
