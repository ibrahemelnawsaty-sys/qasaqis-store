<?php

declare(strict_types=1);

namespace Tests\Feature\Checkout;

use App\Models\Book;
use App\Models\CouponUsage;
use App\Models\Customer;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Support\Cart\Cart;
use Database\Factories\CouponFactory;
use Database\Factories\OrderFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * استهداف الكوبون بالجمهور (audience): all/specific/new/returning/lapsed/vip. يُفحَص
 * خادميًّا. سجلّ العميل يُحسَب من جدول الطلبات (Order::realisedRevenue) لا من العدّادات.
 * العميل يُعرَف بمُعرّفه (مسجّل) أو بجوّاله (زائر). الزائر بلا هويّة → يؤجَّل الفحص (للدفع).
 *
 * HONESTY (1.3): NOT executed here (MySQL down locally); runs via `php artisan test`.
 */
final class CouponAudienceTest extends TestCase
{
    use RefreshDatabase;

    private function cart(string $price = '200.00'): Cart
    {
        $book = Book::factory()->create(['price' => $price]);

        return app(CartService::class)->fromItems([['book_id' => $book->id, 'qty' => 1]]);
    }

    private function service(): CouponService
    {
        return app(CouponService::class);
    }

    /** طلب محقَّق (مدفوع) للعميل بتاريخٍ اختياريّ — يمرّ بـ realisedRevenue. */
    private function paidOrder(Customer $customer, ?Carbon $at = null): void
    {
        OrderFactory::new()->create([
            'customer_id' => $customer->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'created_at' => $at ?? now(),
        ]);
    }

    public function test_all_audience_passes_for_everyone_including_guests(): void
    {
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'all']);

        $this->assertTrue($this->service()->validate($coupon, $this->cart())->valid);
    }

    public function test_specific_audience_allows_only_the_target_customer(): void
    {
        $target = Customer::factory()->create();
        $other = Customer::factory()->create();
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'specific', 'customer_id' => $target->id]);

        $this->assertTrue($this->service()->validate($coupon, $this->cart(), $target->id)->valid);

        $rejected = $this->service()->validate($coupon, $this->cart(), $other->id);
        $this->assertFalse($rejected->valid);
        $this->assertSame('payment.coupon.audience_specific', $rejected->messageKey);
    }

    public function test_specific_audience_matches_a_guest_by_phone(): void
    {
        $target = Customer::factory()->create();
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'specific', 'customer_id' => $target->id]);

        // زائرة بجوّال العميلة المستهدَفة (بالصيغة المحليّة) → تُطابَق وتُقبَل.
        $this->assertTrue($this->service()->validate($coupon, $this->cart(), null, '0'.$target->phone_normalized)->valid);

        // زائرة بجوّال آخر → تُرفَض.
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), null, '01099998888')->valid);
    }

    public function test_new_audience_allows_first_time_buyers_only(): void
    {
        $fresh = Customer::factory()->create();
        $returning = Customer::factory()->create();
        $this->paidOrder($returning);
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'new']);

        $this->assertTrue($this->service()->validate($coupon, $this->cart(), $fresh->id)->valid);

        $rejected = $this->service()->validate($coupon, $this->cart(), $returning->id);
        $this->assertFalse($rejected->valid);
        $this->assertSame('payment.coupon.audience_new', $rejected->messageKey);

        // زائرة بجوّال لا يطابق أي عميل = جديدة → تُقبَل.
        $this->assertTrue($this->service()->validate($coupon, $this->cart(), null, '01055554444')->valid);
    }

    public function test_returning_audience_requires_a_prior_realised_order(): void
    {
        $returning = Customer::factory()->create();
        $this->paidOrder($returning);
        $fresh = Customer::factory()->create();
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'returning']);

        $this->assertTrue($this->service()->validate($coupon, $this->cart(), $returning->id)->valid);
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), $fresh->id)->valid);
    }

    public function test_lapsed_audience_targets_customers_inactive_beyond_the_window(): void
    {
        $lapsed = Customer::factory()->create();
        $this->paidOrder($lapsed, now()->subDays(90));
        $active = Customer::factory()->create();
        $this->paidOrder($active, now()->subDays(10));
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'lapsed', 'lapsed_days' => 60]);

        $this->assertTrue($this->service()->validate($coupon, $this->cart(), $lapsed->id)->valid);
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), $active->id)->valid);
        // عميلة جديدة (بلا طلبات) ليست «متوقّفة».
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), Customer::factory()->create()->id)->valid);
    }

    public function test_vip_audience_by_order_count(): void
    {
        $vip = Customer::factory()->create();
        $this->paidOrder($vip);
        $this->paidOrder($vip);
        $this->paidOrder($vip);
        $normal = Customer::factory()->create();
        $this->paidOrder($normal);
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'vip', 'min_orders' => 3]);

        $this->assertTrue($this->service()->validate($coupon, $this->cart(), $vip->id)->valid);
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), $normal->id)->valid);
    }

    public function test_cancelled_orders_do_not_count_as_a_purchase(): void
    {
        $customer = Customer::factory()->create();
        OrderFactory::new()->create([
            'customer_id' => $customer->id,
            'status' => 'cancelled',
            'payment_status' => 'failed',
        ]);
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'returning']);

        // طلب ملغى فقط → ليست «عائدة».
        $this->assertFalse($this->service()->validate($coupon, $this->cart(), $customer->id)->valid);
    }

    public function test_audience_is_deferred_for_an_unidentified_guest_preview(): void
    {
        $target = Customer::factory()->create();
        $coupon = CouponFactory::new()->percentage(10)->create(['audience' => 'specific', 'customer_id' => $target->id]);

        // معاينة الزائرة (بلا مُعرّف/جوّال/بريد) → يؤجَّل فحص الجمهور (يُفرَض عند الدفع) → صالح.
        $this->assertTrue($this->service()->validate($coupon, $this->cart())->valid);
    }

    public function test_validate_carries_the_resolved_customer_for_usage_recording(): void
    {
        // المُعرّف الذي يُسجَّل به الاستخدام: مُعرّف العميلة (مسجّلة) أو المطابَقة بجوّالها (زائرة).
        $customer = Customer::factory()->create();
        $coupon = CouponFactory::new()->percentage(10)->create();

        $this->assertSame($customer->id, $this->service()->validate($coupon, $this->cart(), $customer->id)->customerId);
        $this->assertSame($customer->id, $this->service()->validate($coupon, $this->cart(), null, '0'.$customer->phone_normalized)->customerId);
    }

    public function test_per_customer_limit_blocks_a_resolved_guest_by_phone(): void
    {
        // ثغرة أُصلِحت: العميلة تستخدمه مرّة، ثم تُتمّ الطلب كزائرة بجوّالها — يجب أن تُمنَع.
        $customer = Customer::factory()->create();
        $coupon = CouponFactory::new()->perUserLimit(1)->create();
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
            'discount_amount' => '10.00',
        ]);

        $result = $this->service()->validate($coupon, $this->cart(), null, '0'.$customer->phone_normalized);

        $this->assertFalse($result->valid);
        $this->assertSame('payment.coupon.user_limit', $result->messageKey);
    }

    public function test_guest_order_history_is_counted_by_email(): void
    {
        // طلب سابق كزائرة (customer_id فارغ) ببريد العميلة → يُحتسَب في سجلّها (عائدة).
        $customer = Customer::factory()->create();
        OrderFactory::new()->create([
            'customer_id' => null,
            'customer_email' => $customer->email,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        $returning = CouponFactory::new()->percentage(10)->create(['audience' => 'returning']);
        $this->assertTrue($this->service()->validate($returning, $this->cart(), $customer->id)->valid);

        $new = CouponFactory::new()->percentage(10)->create(['audience' => 'new']);
        $this->assertFalse($this->service()->validate($new, $this->cart(), $customer->id)->valid);
    }
}
