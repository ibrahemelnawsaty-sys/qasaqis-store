<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * تتحقق أن معلَم البنية التحتية (M1) سجّل المهام المجدولة في bootstrap/app.php
 * (withSchedule) فعلًا: النسخ الاحتياطي والتنظيف والمراقبة والطابور. تفحص هذه
 * الاختبارات سلك الجدولة نفسه — لا تنفيذ الأوامر — فتعمل دون الحاجة لتثبيت حزمة
 * النسخ (command() يبني سلسلة الأمر ولا يستدعي الصنف).
 *
 * withSchedule يسجّل مهامه عبر Artisan::starting، أي حين يُبنى تطبيق Artisan
 * فقط (كما في `php artisan schedule:run`) — لا عند إقلاع تطبيق الاختبار. لذا
 * نُقلع Artisan قبل قراءة المهام، وإلّا لم يُرَ منها إلا ما في routes/console.php.
 */
final class ScheduledTasksTest extends TestCase
{
    /** @return array<int, Event> */
    private function scheduleEvents(): array
    {
        $this->app->make(ConsoleKernel::class)->all();

        return app(Schedule::class)->events();
    }

    /** @return Collection<int, string> */
    private function scheduledCommands(): Collection
    {
        return collect($this->scheduleEvents())
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

    public function test_queue_worker_runs_every_minute(): void
    {
        // cron الاستضافة (كل دقيقة) هو المشغّل الأساسيّ للمُجدول، فأي تباعد هنا
        // يؤخّر إيميلات الطلبات بالقدر نفسه.
        $worker = collect($this->scheduleEvents())
            ->first(fn ($event) => str_contains((string) ($event->command ?? ''), 'queue:work'));

        $this->assertNotNull($worker, 'توقّعت مهمة queue:work مجدولة.');
        $this->assertSame('* * * * *', $worker->expression);
    }

    public function test_web_requests_do_not_tick_the_scheduler(): void
    {
        // كان KeepQueueAlive يشغّل schedule:run داخل عامل PHP لزائر حقيقيّ (بطء
        // الموقع)؛ صار التشغيل بالـcron، فلا يُعاد تسجيله عالميًّا ولا في أي مجموعة.
        $kernel = app(Kernel::class);
        $registered = collect($kernel->getMiddlewareGroups())
            ->flatten()
            ->merge($kernel->getGlobalMiddleware());

        $this->assertNotContains('App\Http\Middleware\KeepQueueAlive', $registered);
    }
}
