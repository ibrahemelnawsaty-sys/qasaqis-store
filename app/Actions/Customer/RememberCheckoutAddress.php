<?php

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\Customer;

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
            'country_code' => $data['country_code'] ?: 'EG',
            'governorate' => $data['governorate'] ?? null,
            'state_province' => $data['state_province'] ?? null,
            'city' => $data['city'] ?? null,
            'address_line' => $data['address_line'] ?? '',
            'address_notes' => $data['address_notes'] ?? null,
        ];

        // مطابقة عنوان قائم بنفس السطر/المحافظة/المدينة (null تُطابَق null في Eloquent).
        $existing = $customer->addresses()
            ->where('address_line', $fields['address_line'])
            ->where('governorate', $fields['governorate'])
            ->where('city', $fields['city'])
            ->first();

        // عنوان افتراضيّ واحد فقط: نُلغيه عن الكلّ ثم نُثبّت المختار.
        $customer->addresses()->update(['is_default' => false]);

        if ($existing !== null) {
            $existing->update($fields + ['is_default' => true]);

            return;
        }

        $customer->addresses()->create($fields + [
            'label' => $this->autoLabel($customer, $fields),
            'is_default' => true,
        ]);
    }

    /** تسمية تلقائية من المحافظة/المدينة، وإلا «عنوان N». تُعدَّل لاحقًا من الملف. */
    private function autoLabel(Customer $customer, array $fields): string
    {
        $base = $fields['governorate'] ?: ($fields['city'] ?: ('عنوان '.($customer->addresses()->count() + 1)));

        return mb_substr((string) $base, 0, 60);
    }
}
