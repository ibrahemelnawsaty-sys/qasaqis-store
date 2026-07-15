<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Models\Order;

/**
 * يبحث عن طلب ضيف بمطابقة رقم الطلب + رقم الجوال (M3). لا يكشف سبب الفشل: يعيد
 * null سواء لم يوجد الرقم أو لم يطابق الجوال، فيتعذّر تعداد الطلبات. المطابقة على
 * آخر 10 أرقام من الجوال (بعد تجريد غير الأرقام) لتحمّل اختلاف البادئة
 * (+20/20/0)، ويقبل الجوال الأساسي أو البديل المسجّلين في الطلب.
 */
class FindGuestOrderAction
{
    public function execute(string $orderNumber, string $phone): ?Order
    {
        // order_number فريد (فهرس unique) — صفّ واحد بلا N+1.
        $order = Order::query()->where('order_number', $orderNumber)->first();

        // نطبّع ونقارن دائمًا (حتى عند غياب الطلب) لتقليل قناة التوقيت التي تميّز
        // رقم طلب موجود عن وهمي. hash_equals يقارن بلا تسرّب زمني للمحتوى.
        // الحماية الأساسية تبقى throttle + فضاء رقم الطلب المتفرّق.
        $input = $this->normalize($phone);
        $primary = $this->normalize($order?->customer_phone);
        $alt = $this->normalize($order?->customer_phone_alt);

        $matches = $input !== ''
            && (hash_equals($primary, $input) || ($alt !== '' && hash_equals($alt, $input)));

        return ($order !== null && $matches) ? $order : null;
    }

    /** آخر 10 أرقام بعد تجريد كل ما ليس رقمًا. */
    private function normalize(?string $phone): string
    {
        return substr((string) preg_replace('/\D/', '', (string) $phone), -10);
    }
}
