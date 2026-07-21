<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;

/**
 * يحفظ عنوان الدفع في دفتر عناوين العميلة (M12): يُحدّث عنوانًا مطابقًا إن وُجد أو
 * يُنشئ جديدًا مُسمّى تلقائيًّا، ويجعله الافتراضيّ (فيُملأ في الطلب القادم).
 *
 * best-effort: يُستدعى داخل try/catch في الدفع، فلا يكسر طلبًا اكتمل (بند المراجعة).
 * الأعمدة سُعِّرت لتتّسع لحدود التحقّق، فلا فيض؛ والتسمية تُقصّ دفاعيًّا على 60.
 */
final class RememberCheckoutAddress
{
    /**
     * @param  array<string, string|null>  $data  حقول العنوان من CheckoutRequest المُتحقَّق.
     */
    public function handle(Customer $customer, array $data): void
    {
        $fields = [
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'phone_alt' => $data['phone_alt'] ?? null,
            'country_code' => ($data['country_code'] ?? null) ?: 'EG',
            'governorate' => $data['governorate'] ?? null,
            'state_province' => $data['state_province'] ?? null,
            'city' => $data['city'] ?? null,
            'address_line' => $data['address_line'] ?? '',
            'address_notes' => $data['address_notes'] ?? null,
        ];

        // مطابقة عنوان قائم بنفس **المستلِم** (الاسم/الجوال) و**الموقع** (السطر/المحافظة/
        // المدينة). إدراج الاسم/الجوال يمنع طمس مستلِم مختلف يُشحن لنفس المبنى (توصيل
        // لقريب): بيانات مختلفة → عنوان جديد، لا تحديث فوق القديم. (null تُطابَق null.)
        $existing = $customer->addresses()
            ->where('name', $fields['name'])
            ->where('phone', $fields['phone'])
            ->where('address_line', $fields['address_line'])
            ->where('governorate', $fields['governorate'])
            ->where('city', $fields['city'])
            ->first();

        // «عنوان واحد افتراضيّ» + الكتابة على عنوانين (تصفير الكلّ ثم تثبيت المختار)
        // في معاملة واحدة: إمّا أن ينجح الحفظ كاملًا أو لا يترك العميلة بلا افتراضيّ.
        DB::transaction(function () use ($customer, $fields, $existing): void {
            $customer->addresses()->update(['is_default' => false]);

            if ($existing !== null) {
                $existing->update($fields + ['is_default' => true]);

                return;
            }

            $customer->addresses()->create($fields + [
                'label' => $this->autoLabel($customer, $fields),
                'is_default' => true,
            ]);
        });
    }

    /**
     * تسمية تلقائية مبدئية: المحافظة والمدينة معًا لتمييز عنوانين بنفس المحافظة
     * (بيتها وبيت أمّها بالقاهرة)، وإلا «عنوان N». تُعيد الأم تسميتها من الملف.
     */
    private function autoLabel(Customer $customer, array $fields): string
    {
        $parts = array_filter([$fields['governorate'] ?? null, $fields['city'] ?? null]);
        $base = $parts === [] ? ('عنوان '.($customer->addresses()->count() + 1)) : implode(' · ', $parts);

        return mb_substr($base, 0, 60);
    }
}
