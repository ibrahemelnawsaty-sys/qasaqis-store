<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Whitelist validation for the public catalogue listing (books.index, category
 * pages). Every externally supplied filter is validated here — never trusted raw
 * from the Request (constitution 4.1 / 2.4). Values map 1:1 to the facets in
 * resources/views/partials/filters.blade.php and the sorts in FiltersBooks.
 */
class CatalogFilterRequest extends FormRequest
{
    /**
     * Public storefront — no authorization gate.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Valid age-bucket keys (mirror FiltersBooks::$ageBuckets / catalog.ages).
     *
     * @var array<int, string>
     */
    protected array $ageBuckets = ['0-3', '3-6', '6-9', '9-99'];

    /**
     * Valid sort keys (mirror FiltersBooks::applySort / catalog.index sortOptions).
     *
     * @var array<int, string>
     */
    protected array $sorts = ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],

            'cat' => ['nullable', 'array'],
            'cat.*' => ['integer', Rule::exists('categories', 'id')],

            'pub' => ['nullable', 'array'],
            'pub.*' => ['integer', Rule::exists('publishers', 'id')],

            'age' => ['nullable', 'array'],
            'age.*' => [Rule::in($this->ageBuckets)],

            'min' => ['nullable', 'numeric', 'min:0'],
            'max' => ['nullable', 'numeric', 'min:0'],

            'sale' => ['nullable', 'boolean'],
            'featured' => ['nullable', 'boolean'],
            'stock' => ['nullable', 'boolean'],

            'sort' => ['nullable', Rule::in($this->sorts)],
        ];
    }
}
