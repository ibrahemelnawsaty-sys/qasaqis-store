<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the AJAX coupon-preview request. The cart is read server-side from
 * the session (never trusting a client total); this only carries the code.
 */
class CouponApplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'coupon' => ['required', 'string', 'max:50'],
        ];
    }
}
