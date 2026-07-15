<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Console\Events\CommandStarting;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * تتحقق من إعدادات البنية التحتية (M1): ماذا يُنسَخ وأين، وأن قرص النسخ معزول
 * ويفشل بصوت مسموع، وأن Sentry لا يسرّب بيانات المستخدمين افتراضيًا. تقرأ
 * الإعداد فقط (لا تتطلب تثبيت الحزم — مراجع ::class في الإعداد سلاسل لا تُحمَّل).
 *
 * HONESTY (1.3/1.5): لم تُشغَّل في هذه البيئة الخالية من PHP؛ تعمل تحت
 * `php artisan test` على الاستضافة/CI.
 */
final class OpsConfigTest extends TestCase
{
    public function test_backup_includes_only_payment_proofs_not_the_codebase(): void
    {
        $include = config('backup.backup.source.files.include');

        $this->assertSame(
            [storage_path('app/private/payment-proofs')],
            $include,
            'النسخ يجب أن يشمل إثباتات الدفع فقط — لا قاعدة الكود (محفوظة في Git).'
        );

        // لا تُدرج الكود ولا الجذر إطلاقًا.
        $this->assertNotContains(base_path(), $include);
        $this->assertNotContains(app_path(), $include);
    }

    public function test_backup_excludes_vendor_and_node_modules(): void
    {
        $exclude = config('backup.backup.source.files.exclude');

        $this->assertContains(base_path('vendor'), $exclude);
        $this->assertContains(base_path('node_modules'), $exclude);
    }

    public function test_backup_targets_the_default_database_connection(): void
    {
        $this->assertSame(
            [config('database.default')],
            config('backup.backup.source.databases')
        );
    }

    public function test_backup_writes_to_the_isolated_external_disk(): void
    {
        $this->assertSame(['backup'], config('backup.backup.destination.disks'));

        $disk = config('filesystems.disks.backup');

        $this->assertIsArray($disk, 'قرص backup يجب أن يكون معرّفًا في filesystems.');
        $this->assertSame('s3', $disk['driver']);
        // يجب أن يفشل بصوت مسموع لا صامت حتى لا يُظنّ أن النسخ يعمل وهو معطّل.
        $this->assertTrue($disk['throw']);
    }

    public function test_backup_archive_encryption_is_enabled(): void
    {
        // التشفير خط الدفاع الوحيد لأرشيف يحوي بيانات العملاء على وجهة خارجية.
        // (غياب كلمة المرور في الإنتاج يمنعه الحارس في AppServiceProvider.)
        $this->assertSame('default', config('backup.backup.encryption'));
    }

    public function test_sentry_does_not_leak_pii_by_default(): void
    {
        $this->assertFalse((bool) config('sentry.send_default_pii'));
        // لا تُسجَّل قيم الاستعلامات (قد تحمل PII) — لا في السجلّ ولا في الآثار.
        $this->assertFalse((bool) config('sentry.breadcrumbs.sql_bindings'));
        $this->assertFalse((bool) config('sentry.tracing.sql_bindings'));
    }

    public function test_production_backup_run_without_password_is_blocked(): void
    {
        // الحارس يمنع رفع أرشيف غير مشفّر (بيانات عملاء) إلى وجهة خارجية.
        $this->app['env'] = 'production';
        config(['backup.backup.password' => null]);

        $this->expectException(RuntimeException::class);

        event(new CommandStarting('backup:run', new ArrayInput([]), new BufferedOutput()));
    }

    public function test_backup_run_is_allowed_when_password_is_set(): void
    {
        $this->app['env'] = 'production';
        config(['backup.backup.password' => 'a-strong-secret']);

        // لا استثناء عندما يكون التشفير مضبوطًا.
        event(new CommandStarting('backup:run', new ArrayInput([]), new BufferedOutput()));

        $this->assertTrue(true);
    }

    public function test_backup_guard_is_inert_outside_production(): void
    {
        // في بيئة الاختبار/التطوير لا يُعطّل الأمر حتى بلا كلمة مرور.
        config(['backup.backup.password' => null]);

        event(new CommandStarting('backup:run', new ArrayInput([]), new BufferedOutput()));

        $this->assertTrue(true);
    }
}
