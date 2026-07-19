<?php

declare(strict_types=1);

namespace App\Support\Checkout;

/**
 * Validated, server-trusted input for PlaceOrderAction. Built from
 * CheckoutRequest — prices are NOT part of this DTO; they are recomputed from
 * the DB inside the action (constitution 4.1).
 */
final readonly class PlaceOrderData
{
    /**
     * @param  array<int, array{book_id: int, qty: int}>  $items
     */
    public function __construct(
        public array $items,
        public string $customerName,
        public string $customerPhone,
        public ?string $customerPhoneAlt,
        public ?string $customerEmail,
        public string $countryCode,
        public ?string $governorate,
        public ?string $stateProvince,
        public ?string $city,
        public string $addressLine,
        public ?string $addressNotes,
        public string $paymentMethod,
        public ?string $couponCode,
        public ?string $customerNote,
        public ?int $userId,
        public ?string $ipAddress,
        // مفتاح منع التكرار (M7): يُشتق من الجلسة في CheckoutRequest، لا من حقل
        // يرسله العميل — كي لا يستطيع أحد إعادة تشغيل مفتاح طلب غيره. null يعني
        // «بلا حماية» (مسار لا يمرّ بصفحة الدفع) ولا يُسقِط الطلب.
        public ?string $idempotencyKey,
        // بيانات إسناد التتبّع (M6) — تُلتقط من كوكيز المتصفح لحدث الشراء الخادمي.
        public ?string $fbp = null,
        public ?string $fbc = null,
        public ?string $gaClientId = null,
        public ?string $gaSessionId = null,
        public ?string $userAgent = null,
        public ?string $eventSourceUrl = null,
        public bool $adsConsent = false,
    ) {
    }
}
