<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use App\Actions\Order\FindGuestOrderAction;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * مطابقة طلب الضيف (M3): آخر 10 أرقام من الجوال (أساسي أو بديل)، ولا يكشف سبب
 * الفشل (يعيد null دائمًا). يحتاج قاعدة بيانات (يستعلم عن order_number الفريد).
 *
 * HONESTY (1.3/1.5): لم تُشغَّل هنا (لا PHP)؛ تعمل عبر `php artisan test` (MySQL).
 */
final class FindGuestOrderActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): FindGuestOrderAction
    {
        return app(FindGuestOrderAction::class);
    }

    public function test_matches_primary_phone_ignoring_prefix(): void
    {
        $order = OrderFactory::new()->create(['customer_phone' => '+201012345678']);

        $found = $this->action()->execute($order->order_number, '01012345678');

        $this->assertNotNull($found);
        $this->assertSame($order->id, $found->id);
    }

    public function test_matches_alternate_phone(): void
    {
        $order = OrderFactory::new()->create([
            'customer_phone' => '01011111111',
            'customer_phone_alt' => '01022222222',
        ]);

        $this->assertNotNull($this->action()->execute($order->order_number, '01022222222'));
    }

    public function test_returns_null_on_wrong_phone(): void
    {
        $order = OrderFactory::new()->create(['customer_phone' => '01012345678']);

        $this->assertNull($this->action()->execute($order->order_number, '01099999999'));
    }

    public function test_returns_null_on_unknown_order_number(): void
    {
        OrderFactory::new()->create(['customer_phone' => '01012345678']);

        $this->assertNull($this->action()->execute('QSQ-2026-NOPEXX', '01012345678'));
    }
}
