<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * منطق مؤشّر «المتصفّحون الآن» (LiveVisitorsWidget): يعدّ الجلسات النشِطة خلال آخر
 * 5 دقائق من جدول sessions (SESSION_DRIVER=database)، ويميّز الزائر (user_id فارغ)
 * عن المسجَّل. يحرس دلالة الاستعلام الذي يقوم عليه الودجت.
 *
 * أمانة (1.3/1.5): لم يُنفَّذ هنا — لا PHP في بيئة التطوير. يُشغَّل على الاستضافة
 * بـ `php artisan test --filter=LiveVisitorsWidgetTest`.
 */
final class LiveVisitorsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(string $id, int $minutesAgo, ?int $userId): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->subMinutes($minutesAgo)->getTimestamp(),
        ]);
    }

    public function test_counts_only_sessions_active_within_five_minutes(): void
    {
        $user = User::factory()->create();

        $this->seedSession('s-now', 0, null);           // نشِط · زائر
        $this->seedSession('s-3m', 3, $user->id);       // نشِط · مسجَّل
        $this->seedSession('s-10m', 10, null);          // خارج النافذة

        $activeSince = now()->subMinutes(5)->getTimestamp();
        $base = DB::table('sessions')->where('last_activity', '>=', $activeSince);

        $this->assertSame(2, (clone $base)->count(), 'الإجمالي النشِط');
        $this->assertSame(1, (clone $base)->whereNull('user_id')->count(), 'الزوّار غير المسجَّلين');
        $this->assertSame(1, (clone $base)->whereNotNull('user_id')->count(), 'المسجَّلون');
    }

    public function test_reports_zero_when_no_recent_activity(): void
    {
        $this->seedSession('old', 30, null);

        $active = DB::table('sessions')
            ->where('last_activity', '>=', now()->subMinutes(5)->getTimestamp())
            ->count();

        $this->assertSame(0, $active);
    }
}
