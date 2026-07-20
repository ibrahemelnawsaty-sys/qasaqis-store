<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Exceptions\CheckoutException;
use App\Models\Country;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Cart\CartService;
use App\Services\Coupon\CouponService;
use App\Services\Notifications\OrderNotifier;
use App\Services\Payment\PaymentGatewayFactory;
use App\Services\Payment\PaymentMethodResolver;
use App\Support\Cart\Cart;
use App\Support\Checkout\OrderPlacementResult;
use App\Support\Checkout\PlaceOrderData;
use App\Support\Coupon\CouponResult;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an order atomically from validated checkout data.
 *
 * Invariants (constitution):
 *  - 4.1: the cart is rebuilt and RE-PRICED from the DB inside the transaction;
 *    no client price/total is trusted. The coupon discount is recomputed too.
 *  - 3.5: the whole write (order + items + coupon usage + payment) is wrapped in
 *    a single DB::transaction with the book rows locked (lockForUpdate).
 *  - 3.5/27: all money is decimal via Money (bcmath), never float.
 *
 * Status mapping per payment method type (values are the ACTUAL DB enums):
 *  - cash_on_delivery : order.status=confirmed, payment_status=unpaid (collected
 *    on delivery; the enum has no "pending" payment_status).
 *  - manual_transfer  : order.status=pending, payment_status=pending_review, and
 *    a payments row (status=pending_review). Customer then uploads proof.
 *  - online_gateway   : order.status=pending, payment_status=unpaid, a payments
 *    row (status=pending), then the gateway is initiated after commit.
 */
class PlaceOrderAction
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CouponService $couponService,
        private readonly PaymentMethodResolver $methodResolver,
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly OrderNotifier $notifier,
    ) {}

    public function execute(PlaceOrderData $data): OrderPlacementResult
    {
        // Resolve + whitelist the payment method (also rejects a hidden online
        // gateway). Type drives the status mapping below.
        $method = $this->methodResolver->find($data->paymentMethod);

        if ($method === null) {
            throw CheckoutException::invalidPaymentMethod();
        }

        // منع التكرار (M7): نقرة مزدوجة على «تأكيد الطلب» — شائعة على الشبكات
        // البطيئة — كانت تُنشئ طلبين وتخصم المخزون مرتين. الفحص المسبق يلتقط
        // الحالة الشائعة (الطلب الثاني يصل بعد اكتمال الأول)، والقيد الفريد في
        // قاعدة البيانات يلتقط السباق الحقيقي (طلبان متزامنان) في catch أدناه.
        $replay = $this->findReplay($data->idempotencyKey, $method);

        if ($replay !== null) {
            return $this->resultForReplay($replay);
        }

        // مفتاح موجود لكنه يخصّ طلبًا بطريقة دفع أخرى (تبويبان مفتوحان) ⇒ هذه نيّة
        // مختلفة لا إعادة إرسال. نمضي بلا مفتاح كي لا يصطدم الفهرس الفريد، ولا
        // نُرجع للعميلة طلبًا يطالبها بتحويل بنكي وهي اختارت الدفع عند الاستلام.
        $data = $this->withoutConflictingKey($data, $method);

        try {
            [$order, $onlinePaymentId] = $this->persistOrder($data, $method);
        } catch (QueryException $e) {
            // تصادم الفهرس الفريد على idempotency_key يعني أن طلبًا متزامنًا سبقنا
            // بالمللي ثانية. إن وُجد ذلك الطلب فهذه إعادة إرسال لا خطأ، فنُعيده
            // للعميلة بدل شاشة فشل. أي خطأ قاعدة بيانات آخر يُعاد رميه كما هو.
            $replay = $this->findReplay($data->idempotencyKey, $method);

            if ($replay === null) {
                throw $e;
            }

            return $this->resultForReplay($replay);
        }

        // لقطة إسناد التتبّع (M6) — بعد الـ commit وبأفضل-جهد: الإسناد ثانوي، فأي
        // فشل فيه (مثلًا قيمة طويلة) يجب ألا يُسقِط البيع. purchase_event_id ثابت
        // لمنع ازدواج عدّ الشراء لدى Meta/GA4.
        rescue(fn () => $order->tracking()->create([
            'fbp' => $data->fbp,
            'fbc' => $data->fbc,
            'ga_client_id' => $data->gaClientId,
            'ga_session_id' => $data->gaSessionId,
            'user_agent' => $data->userAgent,
            'event_source_url' => $data->eventSourceUrl,
            'ads_consent' => $data->adsConsent,
            'purchase_event_id' => (string) Str::uuid(),
        ]));

        // إشعار تأكيد الطلب (للعميل) + تنبيه الأدمن — بعد الـ commit لكل المسارات
        // كي لا يُرسَل بريد عن طلب مُرجَع (M4). ShouldQueue فلا يحجب الاستجابة.
        $this->notifier->orderPlaced($order);

        // Online gateway is initiated AFTER commit (keeps external I/O out of the
        // transaction). Manual/COD need no gateway call.
        if ($method->type === 'online_gateway') {
            return $this->initiateOnline($order, $onlinePaymentId);
        }

        return new OrderPlacementResult($order);
    }

    /**
     * طلب سابق بنفس المفتاح **وبنفس طريقة الدفع** = إعادة إرسال حقيقية.
     *
     * withTrashed مقصود: الفهرس الفريد في MySQL يشمل الصفوف المحذوفة ناعمًا، فلو
     * بحثنا بالنطاق العام وحده لأعطى الطلبُ المحذوف null هنا ثم انفجر INSERT بخطأ
     * 1062 فوصلت العميلة إلى صفحة 500 بدل صفحة طلبها.
     *
     * شرط طريقة الدفع يمنع أسوأ حالة في سيناريو التبويبين: أن تُردّ العميلة التي
     * اختارت «الدفع عند الاستلام» إلى طلب إنستاباي يطالبها بتحويل مال — أو العكس.
     */
    private function findReplay(?string $idempotencyKey, PaymentMethod $method): ?Order
    {
        if ($idempotencyKey === null) {
            return null;
        }

        return Order::withTrashed()
            ->where('idempotency_key', $idempotencyKey)
            ->where('payment_method', $method->code)
            ->first();
    }

    /**
     * يُسقِط المفتاح حين يخصّ طلبًا قائمًا بطريقة دفع أخرى — وإلا اصطدم الفهرس
     * الفريد وأسقط طلبًا مشروعًا. الثمن: هذا الطلب بلا حماية تكرار (مقبول: الحالة
     * تتطلّب تبويبين بطريقتَي دفع مختلفتين).
     */
    private function withoutConflictingKey(PlaceOrderData $data, PaymentMethod $method): PlaceOrderData
    {
        if ($data->idempotencyKey === null) {
            return $data;
        }

        $conflict = Order::withTrashed()
            ->where('idempotency_key', $data->idempotencyKey)
            ->where('payment_method', '!=', $method->code)
            ->exists();

        if (! $conflict) {
            return $data;
        }

        return new PlaceOrderData(
            items: $data->items,
            customerName: $data->customerName,
            customerPhone: $data->customerPhone,
            customerPhoneAlt: $data->customerPhoneAlt,
            customerEmail: $data->customerEmail,
            countryCode: $data->countryCode,
            governorate: $data->governorate,
            stateProvince: $data->stateProvince,
            city: $data->city,
            addressLine: $data->addressLine,
            addressNotes: $data->addressNotes,
            paymentMethod: $data->paymentMethod,
            couponCode: $data->couponCode,
            customerNote: $data->customerNote,
            userId: $data->userId,
            ipAddress: $data->ipAddress,
            idempotencyKey: null,
            fbp: $data->fbp,
            fbc: $data->fbc,
            gaClientId: $data->gaClientId,
            gaSessionId: $data->gaSessionId,
            userAgent: $data->userAgent,
            eventSourceUrl: $data->eventSourceUrl,
            adsConsent: $data->adsConsent,
        );
    }

    /**
     * نتيجة إعادة الإرسال. الطلب الأونلاين غير المدفوع يُعاد إطلاق بوابته بدل
     * إرجاعه بلا initiation — وإلا سقط في CheckoutController إلى صفحة الشكر،
     * فتُطمأن العميلة كذبًا إلى طلب لم تدفعه ولا زرَّ دفعٍ فيه، ثم يُلغى تلقائيًا.
     */
    private function resultForReplay(Order $replay): OrderPlacementResult
    {
        $isUnpaidOnline = $replay->payment_method === 'online_gateway'
            && $replay->payment_status === 'unpaid';

        if (! $isUnpaidOnline) {
            return new OrderPlacementResult($replay);
        }

        $pendingPaymentId = $replay->payments()
            ->where('status', 'pending')
            ->latest()
            ->value('id');

        return $this->initiateOnline($replay, $pendingPaymentId === null ? null : (int) $pendingPaymentId);
    }

    /**
     * كتابة الطلب كاملًا في معاملة واحدة مع قفل صفوف الكتب.
     *
     * @return array{0: Order, 1: int|null} [الطلب، معرّف صف الدفع للبوابة إن وُجد]
     */
    private function persistOrder(PlaceOrderData $data, PaymentMethod $method): array
    {
        return DB::transaction(function () use ($data, $method) {
            // Re-price the cart from the DB with the book rows locked.
            $cart = $this->cartService->fromItems($data->items, lock: true);

            if ($cart->isEmpty()) {
                throw CheckoutException::emptyCart();
            }

            $this->assertStockAndReserve($cart);

            $couponResult = $this->resolveCoupon($data, $cart);

            [$shipping, $shippingZoneCode] = $this->resolveShipping($data, $couponResult->freeShipping);
            $discount = $couponResult->discount;
            $grandTotal = Money::clampNonNegative(
                Money::add(Money::sub($cart->subtotal, $discount), $shipping)
            );

            [$status, $paymentStatus] = $this->statusFor($method);

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'idempotency_key' => $data->idempotencyKey,
                'user_id' => $data->userId,
                'status' => $status,
                'customer_name' => $data->customerName,
                'customer_phone' => $data->customerPhone,
                'customer_phone_alt' => $data->customerPhoneAlt,
                'customer_email' => $data->customerEmail,
                'country_code' => $data->countryCode,
                'governorate' => $data->governorate,
                'state_province' => $data->stateProvince,
                'city' => $data->city,
                'address_line' => $data->addressLine,
                'address_notes' => $data->addressNotes,
                'subtotal' => $cart->subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'shipping_zone_code' => $shippingZoneCode,
                'grand_total' => $grandTotal,
                'coupon_id' => $couponResult->valid ? $couponResult->coupon?->id : null,
                'coupon_code' => $couponResult->valid ? $couponResult->coupon?->code : null,
                'payment_method' => $method->code,
                'payment_status' => $paymentStatus,
                'customer_note' => $data->customerNote,
                'ip_address' => $data->ipAddress,
            ]);

            // Snapshot each line (price taken from the DB, not the client).
            foreach ($cart->items as $item) {
                // لقطة التكلفة وقت البيع (المرحلة ٢): من books.cost_price المُحمّل في
                // السلة. تبقى NULL حين لا تكلفة مُدخلة (BOOK1) فلا نخترع صفرًا، وسطرها
                // يُستبعد لاحقًا من COGS. line_cost = التكلفة × الكمية بحساب Money (bcmath).
                $unitCost = $item->book->cost_price !== null
                    ? Money::normalize($item->book->cost_price)
                    : null;
                $lineCost = $unitCost !== null
                    ? Money::multiplyByQty($unitCost, $item->quantity)
                    : null;

                $order->items()->create([
                    'book_id' => $item->book->id,
                    'book_title' => $item->book->title,
                    'unit_price' => $item->unitPrice,
                    'unit_cost' => $unitCost,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal,
                    'line_cost' => $lineCost,
                ]);
            }

            if ($couponResult->valid && $couponResult->coupon instanceof Coupon) {
                $this->recordCouponUsage($order, $couponResult, $data->userId);
            }

            $onlinePaymentId = $this->createPaymentRow($order, $method, $grandTotal);

            return [$order, $onlinePaymentId];
        });
    }

    /**
     * Reject out-of-stock / insufficient lines and decrement managed stock.
     */
    private function assertStockAndReserve(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $book = $item->book;

            if (! $book->manage_stock) {
                continue;
            }

            if ($book->stock_status === 'out_of_stock' || $book->stock_quantity < $item->quantity) {
                throw CheckoutException::outOfStock($book->title);
            }

            $book->stock_quantity -= $item->quantity;

            if ($book->stock_quantity <= 0) {
                $book->stock_quantity = 0;
                $book->stock_status = 'out_of_stock';
            }

            $book->save();
        }
    }

    /**
     * Recompute the coupon against the freshly-priced cart. A supplied-but-now-
     * invalid coupon fails the checkout so the customer never pays a stale total.
     */
    private function resolveCoupon(PlaceOrderData $data, Cart $cart): CouponResult
    {
        if ($data->couponCode === null || trim($data->couponCode) === '') {
            return CouponResult::invalid('payment.coupon.required');
        }

        $result = $this->couponService->apply($data->couponCode, $cart, $data->userId);

        if (! $result->valid) {
            throw new CheckoutException($result->messageKey);
        }

        return $result;
    }

    private function recordCouponUsage(Order $order, CouponResult $result, ?int $userId): void
    {
        /** @var Coupon $coupon */
        $coupon = $result->coupon;

        // Atomically claim one use: increment used_count ONLY while it is still
        // under the global usage_limit. This closes the race where two concurrent
        // checkouts both pass the earlier (non-locking) `used_count < usage_limit`
        // check in CouponService. usage_limit NULL = unlimited (no upper bound in
        // the WHERE, so it always matches). Zero rows affected => the last use was
        // taken by a concurrent order, so we abort and the whole DB::transaction
        // rolls back (no order, no over-redeemed coupon).
        $claimed = Coupon::query()
            ->whereKey($coupon->id)
            ->where(function ($query): void {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->increment('used_count');

        if ($claimed === 0) {
            throw new CheckoutException('payment.coupon.usage_limit');
        }

        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'order_id' => $order->id,
            'user_id' => $userId,
            'discount_amount' => $result->discount,
        ]);
    }

    /**
     * @return int|null the payments row id when one is created for online.
     */
    private function createPaymentRow(Order $order, PaymentMethod $method, string $grandTotal): ?int
    {
        if ($method->type === 'cash_on_delivery') {
            return null; // COD is collected on delivery; no payment row yet.
        }

        $status = $method->type === 'manual_transfer' ? 'pending_review' : 'pending';

        $payment = $order->payments()->create([
            'payment_method_code' => $method->code,
            'amount' => $grandTotal,
            'status' => $status,
        ]);

        return $method->type === 'online_gateway' ? $payment->id : null;
    }

    private function initiateOnline(Order $order, ?int $paymentId): OrderPlacementResult
    {
        $initiation = $this->gatewayFactory->default()->initiate($order);

        if ($paymentId !== null) {
            $order->payments()
                ->whereKey($paymentId)
                ->update([
                    'transaction_ref' => $initiation->reference,
                    'gateway_response' => $initiation->raw,
                ]);
        }

        return new OrderPlacementResult($order, $initiation);
    }

    /**
     * @return array{0: string, 1: string} [order.status, order.payment_status]
     */
    private function statusFor(PaymentMethod $method): array
    {
        return match ($method->type) {
            'cash_on_delivery' => ['confirmed', 'unpaid'],
            'manual_transfer' => ['pending', 'pending_review'],
            'online_gateway' => ['pending', 'unpaid'],
            default => throw CheckoutException::invalidPaymentMethod(),
        };
    }

    /**
     * تحديد تكلفة الشحن ورمز المنطقة (M5). مصر: من config/egypt (تجاوز المحافظة
     * أو الثابت) — تسعير مصر يبقى حرفيًا. دولي: flat_cost لمنطقة الدولة (بالجنيه،
     * التحصيل EGP). كوبون free_shipping يصفّر أيّهما. لا تُخترع أسعار (بند 1.1).
     *
     * @return array{0: string, 1: string|null} [cost, shipping_zone_code]
     */
    private function resolveShipping(PlaceOrderData $data, bool $freeShipping): array
    {
        if ($freeShipping) {
            return [Money::ZERO, null];
        }

        if ($data->countryCode === 'EG') {
            /** @var array<string, string> $overrides */
            $overrides = (array) config('egypt.shipping.overrides', []);
            $flat = (string) config('egypt.shipping.flat', Money::ZERO);

            return [Money::normalize($overrides[$data->governorate] ?? $flat), 'EG'];
        }

        // دولي: منطقة الدولة (eager load لمنع N+1). الدولة مُتحقَّق منها في الطلب.
        $zone = Country::query()->with('shippingZone')
            ->where('iso_code', $data->countryCode)
            ->first()?->shippingZone;

        // فحص دفاعي: منطقة غير موجودة أو معطّلة → لا شحن مُحدَّد، نرفض الطلب بدل
        // قبوله بصفر جنيه (يكمّل حارس is_active في التحقّق).
        if ($zone === null || $zone->is_active !== true) {
            throw new CheckoutException('payment.errors.shipping_unavailable');
        }

        return [Money::normalize((string) $zone->flat_cost), $zone->code];
    }

    /**
     * Unique order number QSQ-YYYY-XXXXXX. The random suffix space is large; we
     * re-roll on the (extremely rare) collision. order_number has a UNIQUE index.
     */
    private function generateOrderNumber(): string
    {
        do {
            $candidate = 'QSQ-'.date('Y').'-'.Str::upper(Str::random(6));
        } while (Order::where('order_number', $candidate)->exists());

        return $candidate;
    }
}
