<?php

declare(strict_types=1);

namespace App\Http\Requests;

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

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $availableCodes = app(PaymentMethodResolver::class)->availableCodes();

        return [
            'name' => ['required', 'string', 'min:2', 'max:150'],
            'phone' => ['required', 'string', 'regex:'.self::EGYPT_PHONE_REGEX],
            'phone_alt' => ['nullable', 'string', 'regex:'.self::EGYPT_PHONE_REGEX],
            'email' => ['nullable', 'email', 'max:191'],

            'governorate' => ['required', 'string', Rule::in(config('egypt.governorates'))],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['required', 'string', 'min:5', 'max:300'],
            'address_notes' => ['nullable', 'string', 'max:300'],

            'payment_method' => ['required', 'string', Rule::in($availableCodes)],

            'coupon' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', Rule::exists('books', 'id')],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('validation.egypt_phone'),
            'phone_alt.regex' => __('validation.egypt_phone'),
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
            governorate: (string) $this->validated('governorate'),
            city: $this->validated('city'),
            addressLine: (string) $this->validated('address'),
            addressNotes: $this->validated('address_notes'),
            paymentMethod: (string) $this->validated('payment_method'),
            couponCode: $this->validated('coupon'),
            customerNote: $this->validated('note'),
            userId: $this->user()?->id,
            ipAddress: $this->ip(),
        );
    }
}
