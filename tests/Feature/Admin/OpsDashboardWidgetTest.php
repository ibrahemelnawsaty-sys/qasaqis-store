<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * منطق ودجت لوحة العمليات (المرحلة ١): «المتصفّحون الآن» وعدّادات «تحتاج إجراءً».
 *
 * يحرس دلالات الاستعلامات التي تعتمدها OverviewStatsWidget و NeedsActionWidget
 * (بيانات مبذورة → عدّ متوقّع). لا يُصيّر مكوّن Filament (لا يوجد نمط لذلك في الطقم)،
 * بل يثبّت السلوك البياني الذي تقوم عليه الودجت. تصيير الودجت يُتحقّق يدويًّا.
 *
 * أمانة (1.3/1.5): لم تُنفَّذ هنا — لا PHP في بيئة التطوير. تُشغَّل على الاستضافة.
 */
final class OpsDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_visitors_counts_only_sessions_active_within_five_minutes(): void
    {
        $user = User::factory()->create();
        $ts = static fn (int $mins): int => now()->subMinutes($mins)->getTimestamp();

        $rows = [
            ['id' => 's-now', 'user_id' => null, 'mins' => 0],          // نشِط، ضيف
            ['id' => 's-3m', 'user_id' => $user->id, 'mins' => 3],      // نشِط، مسجَّل
            ['id' => 's-10m', 'user_id' => null, 'mins' => 10],         // خارج النافذة
        ];
        foreach ($rows as $r) {
            DB::table('sessions')->insert([
                'id' => $r['id'], 'user_id' => $r['user_id'], 'ip_address' => '127.0.0.1',
                'user_agent' => 'test', 'payload' => '', 'last_activity' => $ts($r['mins']),
            ]);
        }

        $activeSince = now()->subMinutes(5)->getTimestamp();

        // إجمالي النشِطين = 2 (استُبعد s-10m).
        $this->assertSame(2, DB::table('sessions')->where('last_activity', '>=', $activeSince)->count());
        // منهم ضيف واحد (user_id فارغ).
        $this->assertSame(1, DB::table('sessions')
            ->where('last_activity', '>=', $activeSince)->whereNull('user_id')->count());
    }

    public function test_needs_action_queue_counts_are_isolated_per_state(): void
    {
        // حالات صريحة غير متداخلة، كلٌّ يقع في طابور واحد فقط.
        Order::factory()->create(['status' => 'pending', 'whatsapp_confirmed_at' => null, 'payment_method' => 'cod']);
        Order::factory()->create(['status' => 'pending', 'whatsapp_confirmed_at' => now(), 'payment_method' => 'cod']);
        Order::factory()->create(['status' => 'confirmed', 'tracking_number' => null, 'payment_method' => 'cod', 'whatsapp_confirmed_at' => now()]);
        Order::factory()->create(['status' => 'shipped', 'payment_method' => 'cod', 'whatsapp_confirmed_at' => now()]);
        Order::factory()->create(['status' => 'delivered', 'payment_method' => 'instapay', 'payment_status' => 'pending_review', 'whatsapp_confirmed_at' => now()]);

        // تنتظر تأكيد واتساب: الأول فقط.
        $this->assertSame(1, Order::where('status', 'pending')->whereNull('whatsapp_confirmed_at')->count());
        // مؤكّد بلا تتبّع: الثالث فقط.
        $this->assertSame(1, Order::whereIn('status', ['confirmed', 'processing'])->whereNull('tracking_number')->count());
        // مشحون: الرابع فقط.
        $this->assertSame(1, Order::where('status', 'shipped')->count());
        // دفع يدوي معلّق: الخامس فقط (غير cod + pending_review).
        $this->assertSame(1, Order::where('payment_method', '!=', 'cod')
            ->whereIn('payment_status', ['unpaid', 'pending_review'])->count());
    }

    public function test_realized_revenue_sums_only_delivered_and_completed(): void
    {
        Order::factory()->create(['status' => 'delivered', 'grand_total' => '300.00']);
        Order::factory()->create(['status' => 'completed', 'grand_total' => '200.00']);
        Order::factory()->create(['status' => 'pending', 'grand_total' => '999.00']);   // لا يُحسب

        $revenue = Order::whereIn('status', ['delivered', 'completed'])
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('grand_total');

        $this->assertEquals(500, (float) $revenue);
    }
}
