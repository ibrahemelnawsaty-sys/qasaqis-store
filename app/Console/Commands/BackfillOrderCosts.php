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

    protected $description = 'يملأ تكلفة الطلبات القديمة (unit_cost/line_cost): سعر الشراء المُدخَل إن وُجد، وإلا تقدير من خصم دار النشر';

    public function handle(CostBackfillService $service): int
    {
        $dry = (bool) $this->option('dry-run');
        $r = $service->run($dry);

        $prefix = $dry ? '[تجريبي — بلا كتابة] ' : '';
        $this->info($prefix.'سطور مُلئت: '.$r['filled'].' في '.$r['orders'].' طلب (منها تقديري من خصم الدار: '.$r['estimated'].').');

        if ($r['skipped'] > 0) {
            $this->warn('سطور تعذّر ترحيلها (كتاب محذوف صلبًا أو بلا سعر بيع): '.$r['skipped']);
        }

        if ($dry && $r['filled'] > 0) {
            $this->line('شغّل الأمر بلا --dry-run للتنفيذ.');
        }

        return self::SUCCESS;
    }
}
