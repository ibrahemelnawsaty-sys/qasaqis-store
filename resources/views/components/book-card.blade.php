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
        'cover' => $book->coverUrl(), // غلاف مصغّر للدرج (موسوم؛ null إن بلا غلاف)
    ];

    // نصّ المشاركة المعبّأ (يطابق شريط صفحة الكتاب) — يُحمل على الزرّ لتقرأه المشاركة الأصليّة/السوشيال.
    $shareText = __('book.share.text', ['title' => $book->title]);
@endphp

<article class="book">
    <x-book-cover :book="$book" :href="$url">
        @if ($discount)
            <span class="disc">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
        @endif
        @if ($book->newBadgeVisible())
            <span class="new-badge"><span>{{ __('book.new_badge') }}</span></span>
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

            <div class="book-actions">
                {{-- مشاركة الكتاب: على الجوال تفتح قائمة الهاتف الأصليّة مباشرةً، وعلى الحاسوب
                     قائمة صغيرة (نسخ الرابط/واتساب/فيسبوك). القائمة والسكربت يُصيَّران مرّة
                     واحدة للصفحة عبر @once أسفل المكوّن (بند 5.2: JS أصلي خفيف، لا مكتبات). --}}
                <button type="button" class="book-share"
                    data-share-url="{{ $url }}"
                    data-share-title="{{ $book->title }}"
                    data-share-text="{{ $shareText }}"
                    aria-haspopup="true" aria-expanded="false"
                    aria-label="{{ __('book.share.card_aria', ['title' => $book->title]) }}"
                    title="{{ __('book.share.label') }}">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>

                <button type="button" class="add"
                    @if ($canBuy)
                        @click="$store.cart.add({{ \Illuminate\Support\Js::from($cartPayload) }})"
                    @else
                        disabled
                    @endif
                    aria-label="{{ __('common.add_to_cart') }}">+</button>
            </div>
        </div>
    </div>
</article>

{{-- قائمة المشاركة المشتركة + معالجها (عنصر واحد للصفحة مهما تعدّدت البطاقات/الأزرار)،
     مُستخرَجة إلى جزئية يشترك فيها الكتب والمقالات. --}}
@include('partials.share-pop')
