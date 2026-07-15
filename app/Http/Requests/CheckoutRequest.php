<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Country;
use App\Services\Payment\PaymentMethodResolver;
use App\Support\Checkout\PlaceOrderData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the checkout submission. Whitelist only (constitution 4.1/2.4);
 * nothing money-related is accepted from the client — prices come from the DB.
 * Egyptian phone format, governorate from config('egypt'), and payment_method
 * restricted to the currently AVAILABLE methods (a hidden online gateway is
 * therefore rejected server-side).
 */
class CheckoutRequest extends FormRequest
{
    /** Egyptian mobile: optional +20/20/0 prefix then 1[0125] + 8 digits. */
    private const EGYPT_PHONE_REGEX = '/^(?:\+?20|0)?1[0125]\d{8}$/';

    /** International E.164: leading + then 8–15 digits (M5). */
    private const INTL_PHONE_REGEX = '/^\+[1-9]\d{7,14}$/';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * توافق خلفي: نماذج قديمة بلا country_code تُعامَل كمصرية (M5).
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('country_code'))) {
            $this->merge(['country_code' => 'EG']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $availableCodes = app(PaymentMethodResolver::class)->availableCodes();

        // مصر (الوطن) مسموحة دائمًا حتى قبل بذر جدول الدول؛ يُضاف إليها كل دولة
        // قابلة للشحن (مفعّلة ومنطقتها مفعّلة). تسعير مصر مرجعه config/egypt.
        $allowedCountries = array_values(array_unique(array_merge(
            ['EG'],
            Country::query()->shippable()->pluck('iso_code')->all(),
        )));

        $isEgypt = $this->input('country_code') === 'EG';
        $phoneRule = 'regex:'.($isEgypt ? self::EGYPT_PHONE_REGEX : self::INTL_PHONE_REGEX);

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', $phoneRule],
            'phone_alt' => ['nullable', 'string', $phoneRule],
            'email' => ['nullable', 'email', 'max:191'],

            'country_code' => ['required', 'string', 'size:2', Rule::in($allowedCountries)],
            // مصر → محافظة من القائمة؛ دولي → ولاية/إقليم نصّي.
            'governorate' => $isEgypt
                ? ['required', 'string', Rule::in(config('egypt.governorates'))]
                : ['nullable', 'string', 'max:100'],
            'state_province' => $isEgypt
                ? ['nullable', 'string', 'max:100']
                : ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'min:5', 'max:300'],
            'address_notes' => ['nullable', 'string', 'max:300'],

            'payment_method' => ['required', 'string', Rule::in($availableCodes)],

            'coupon' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', Rule::exists('books', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],

            // إسناد التتبّع (M6) — قوائم بيضاء nullable، لا تمسّ تسعير المال.
            'fbp' => ['nullable', 'string', 'max:100'],
            'fbc' => ['nullable', 'string', 'max:191'],
            'ga_client_id' => ['nullable', 'string', 'max:100'],
            'ga_session_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $phoneMessage = $this->input('country_code') === 'EG'
            ? __('validation.egypt_phone')
            : __('validation.international_phone');

        return [
            'phone.regex' => $phoneMessage,
            'phone_alt.regex' => $phoneMessage,
        ];
    }

    /**
     * Build the server-trusted DTO for PlaceOrderAction.
     */
    public function toData(): PlaceOrderData
    {
        /** @var array<int, array{book_id: int, qty: int}> $items */
        $items = array_map(
            static fn (array $row): array => [
                'book_id' => (int) $row['book_id'],
                'qty' => (int) $row['qty'],
            ],
            $this->validated('items')
        );

        return new PlaceOrderData(
            items: $items,
            customerName: (string) $this->validated('name'),
            customerPhone: (string) $this->validated('phone'),
            customerPhoneAlt: $this->validated('phone_alt'),
            customerEmail: $this->validated('email'),
            countryCode: (string) $this->validated('country_code'),
            governorate: $this->validated('governorate'),
            stateProvince: $this->validated('state_province'),
            city: $this->validated('city'),
            addressLine: (string) $this->validated('address'),
            addressNotes: $this->validated('address_notes'),
            paymentMethod: (string) $this->validated('payment_method'),
            couponCode: $this->validated('coupon'),
            customerNote: $this->validated('note'),
            userId: $this->user()?->id,
            ipAddress: $this->ip(),
            fbp: $this->validated('fbp'),
            fbc: $this->validated('fbc'),
            gaClientId: $this->validated('ga_client_id'),
            gaSessionId: $this->validated('ga_session_id'),
            userAgent: $this->userAgent(),
            eventSourceUrl: $this->headers->get('referer'),
        );
    }
}
