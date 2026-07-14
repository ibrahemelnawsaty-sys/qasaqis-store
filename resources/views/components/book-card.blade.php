@props(['book'])

@php
    $url = route('books.show', $book);
    $hasPrice = $book->price !== null;
    $inStock = $book->stock_status === 'in_stock';
    $canBuy = $hasPrice && $inStock;

    // Struck-through offer: old_price is the (higher) original, price is what you pay.
    $onSale = $hasPrice && $book->old_price !== null && (float) $book->old_price > (float) $book->price;
    $discount = $onSale
        ? (int) round((((float) $book->old_price - (float) $book->price) / (float) $book->old_price) * 100)
        : null;

    // Age display: prefer the admin label, else build from min/max, else nothing.
    $ageText = filled($book->age_label) ? $book->age_label : null;
    if (! $ageText) {
        if ($book->age_min !== null && $book->age_max !== null) {
            $ageText = __('book.age_range', ['min' => $book->age_min, 'max' => $book->age_max]);
        } elseif ($book->age_min !== null) {
            $ageText = __('book.age_from', ['min' => $book->age_min]);
        } elseif ($book->age_max !== null) {
            $ageText = __('book.age_to', ['max' => $book->age_max]);
        }
    }

    $priceDisplay = $hasPrice
        ? number_format((float) $book->price, 0) . ' ' . __('common.currency')
        : null;

    $cartPayload = [
        'id' => $book->id,
        'title' => $book->title,
        'price' => $priceDisplay,
        'url' => $url,
    ];
@endphp

<article class="book">
    <x-book-cover :book="$book" :href="$url">
        @if ($discount)
            <span class="disc">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
        @endif
    </x-book-cover>

    <div class="book-body">
        @if ($book->category)
            <a class="book-cat" href="{{ route('categories.show', $book->category) }}">{{ $book->category->name }}</a>
        @endif

        <a class="book-title" href="{{ $url }}">{{ $book->title }}</a>

        @if ($ageText)
            <div class="book-age">👧 {{ $ageText }}</div>
        @endif

        <div class="book-foot">
            <div class="price">
                @if ($hasPrice)
                    <span class="now">{{ number_format((float) $book->price, 0) }}</span>
                    <span class="cur">{{ __('common.currency') }}</span>
                    @if ($onSale)
                        <span class="old">{{ number_format((float) $book->old_price, 0) }}</span>
                    @endif
                @else
                    <span class="na">{{ __('common.price_unavailable') }}</span>
                @endif
            </div>

            <button type="button" class="add"
                @if ($canBuy)
                    @click="$store.cart.add({{ \Illuminate\Support\Js::from($cartPayload) }})"
                @else
                    disabled
                @endif
                aria-label="{{ __('common.add_to_cart') }}">+</button>
        </div>
    </div>
</article>
