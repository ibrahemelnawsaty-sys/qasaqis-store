<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * تتحقق أن معلَم البنية التحتية (M1) سجّل المهام المجدولة في bootstrap/app.php
 * (withSchedule) فعلًا: النسخ الاحتياطي والتنظيف والمراقبة والطابور. تفحص هذه
 * الاختبارات سلك الجدولة نفسه — لا تنفيذ الأوامر — فتعمل دون الحاجة لتثبيت حزمة
 * النسخ (command() يبني سلسلة الأمر ولا يستدعي الصنف).
 *
 * HONESTY (1.3/1.5): لم تُشغَّل في هذه البيئة الخالية من PHP؛ تعمل تحت
 * `php artisan test` على الاستضافة/CI.
 */
final class ScheduledTasksTest extends TestCase
{
    /** @return Collection<int, string> */
    private function scheduledCommands(): Collection
    {
        return collect(app(Schedule::class)->events())
            ->map(fn ($event) => (string) ($event->command ?? ''))
            ->filter()
            ->values();
    }

    private function assertHasCommandContaining(string $needle): void
    {
        $this->assertTrue(
            $this->scheduledCommands()->contains(fn (string $cmd) => str_contains($cmd, $needle)),
            "توقّعت مهمة مجدولة تحتوي: {$needle}. الموجود: ".$this->scheduledCommands()->implode(' | ')
        );
    }

    public function test_daily_database_backup_is_scheduled(): void
    {
        $this->assertHasCommandContaining('backup:run --only-db');
    }

    public function test_full_backup_is_scheduled(): void
    {
        // نسخة كاملة (بلا --only-db) تشمل إثباتات الدفع أيضًا.
        $hasFull = $this->scheduledCommands()->contains(
            fn (string $cmd) => str_contains($cmd, 'backup:run') && ! str_contains($cmd, '--only-db')
        );

        $this->assertTrue($hasFull, 'توقّعت مهمة backup:run كاملة (بلا --only-db).');
    }

    public function test_backup_cleanup_and_monitor_are_scheduled(): void
    {
        $this->assertHasCommandContaining('backup:clean');
        $this->assertHasCommandContaining('backup:monitor');
    }

    public function test_queue_worker_is_scheduled(): void
    {
        // الطابور يُعالَج عبر المُجدول على الاستضافة المشتركة (بلا Supervisor).
        $this->assertHasCommandContaining('queue:work');
        $this->assertHasCommandContaining('--stop-when-empty');
    }
}
