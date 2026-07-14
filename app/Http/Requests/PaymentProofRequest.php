<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a manual-transfer proof upload (constitution 4.5).
 * Whitelist of real file types + size cap; the file is stored on a private disk
 * with a random name by the controller — the original name is never trusted.
 */
class PaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access to the order is enforced by the signed route (guest-safe link).
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // required + real extension whitelist (jpg/jpeg/png/pdf) + 4 MB cap.
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'sender_reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}
