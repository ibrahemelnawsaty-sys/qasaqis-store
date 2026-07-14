<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates cart mutations. The cart itself stores only {book_id: qty}; prices
 * are always resolved from the DB at render/checkout time, never stored client
 * side (constitution 4.1).
 */
class CartUpdateRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.book_id' => ['required', 'integer', Rule::exists('books', 'id')],
            // qty 0 is allowed here so a line can be removed via update.
            'items.*.qty' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }
}
