<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * منطق FulfillmentTimingWidget: زمن الوصول لكل مرحلة = أوّل انتقال إليها ناقص
 * وقت إنشاء الطلب، محسوب من order_status_histories عبر MIN(CASE WHEN) + fromSub.
 * يحرس دلالة الاستعلام (يعمل على MariaDB كبيئة الاستضافة — TIMESTAMPDIFF/CASE).
 *
 * أمانة (1.3/1.5): لم يُنفَّذ هنا — لا PHP في بيئة التطوير. يُشغَّل على الاستضافة.
 */
final class FulfillmentTimingTest extends TestCase
{
    use RefreshDatabase;

    private function history(int $orderId, string $from, string $to, Carbon $at): void
    {
        DB::table('order_status_histories')->insert([
            'order_id' => $orderId,
            'from_status' => $from,
            'to_status' => $to,
            'note' => null,
            'actor_id' => null,
            'source' => 'system',
            'created_at' => $at,
        ]);
    }

    /** نكرّر استعلام الودجت حرفيًّا لنثبّت دلالته. */
    private function timing(): object
    {
        $milestones = DB::table('order_status_histories')
            ->groupBy('order_id')
            ->selectRaw(
                'order_id,'
                ." MIN(CASE WHEN to_status = 'confirmed' THEN created_at END) as confirmed_at,"
                ." MIN(CASE WHEN to_status = 'shipped' THEN created_at END) as shipped_at,"
                ." MIN(CASE WHEN to_status IN ('delivered', 'completed') THEN created_at END) as delivered_at"
            );

        return DB::query()
            ->fromSub($milestones, 'm')
            ->join('orders as o', 'o.id', '=', 'm.order_id')
            ->selectRaw(
                'AVG(TIMESTAMPDIFF(MINUTE, o.created_at, m.confirmed_at)) as min_confirmed,'
                .' COUNT(m.confirmed_at) as n_confirmed,'
                .' COUNT(m.delivered_at) as n_delivered'
            )
            ->first();
    }

    public function test_lead_time_to_confirmed_averages_from_order_creation(): void
    {
        $base = Carbon::parse('2026-07-01 10:00:00');

        $a = Order::factory()->create(['status' => 'confirmed', 'created_at' => $base]);
        $b = Order::factory()->create(['status' => 'confirmed', 'created_at' => $base]);

        // A تأكّد بعد ساعتين، B بعد 4 ساعات → المتوسّط 180 دقيقة.
        $this->history($a->id, 'pending', 'confirmed', (clone $base)->addHours(2));
        $this->history($b->id, 'pending', 'confirmed', (clone $base)->addHours(4));

        $t = $this->timing();

        $this->assertSame(2, (int) $t->n_confirmed);
        $this->assertEqualsWithDelta(180, (float) $t->min_confirmed, 0.5);
        // لا تسليمات بعد → العدّاد صفر (تظهر «—» في الودجت).
        $this->assertSame(0, (int) $t->n_delivered);
    }

    public function test_uses_first_transition_to_a_status_not_later_ones(): void
    {
        $base = Carbon::parse('2026-07-01 10:00:00');
        $o = Order::factory()->create(['status' => 'delivered', 'created_at' => $base]);

        // تسلسل: تأكيد (1س) → شحن (3س) → تسليم (6س).
        $this->history($o->id, 'pending', 'confirmed', (clone $base)->addHours(1));
        $this->history($o->id, 'confirmed', 'shipped', (clone $base)->addHours(3));
        $this->history($o->id, 'shipped', 'delivered', (clone $base)->addHours(6));

        $t = $this->timing();

        $this->assertSame(1, (int) $t->n_confirmed);
        $this->assertSame(1, (int) $t->n_delivered);
        $this->assertEqualsWithDelta(60, (float) $t->min_confirmed, 0.5);
    }
}
