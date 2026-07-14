@extends('layouts.app')

@php
    $metaTitle = $book->seo?->meta_title ?: $book->title . ' — ' . __('common.brand');
    $metaDesc = $book->seo?->meta_description ?: ($book->short_description ?? __('common.tagline'));

    $hasPrice = $book->price !== null;
    $inStock = $book->stock_status === 'in_stock';
    $canBuy = $hasPrice && $inStock;
    $onSale = $hasPrice && $book->old_price !== null && (float) $book->old_price > (float) $book->price;
    $saveAmount = $onSale ? (int) round((float) $book->old_price - (float) $book->price) : 0;
    $discount = $onSale ? (int) round((((float) $book->old_price - (float) $book->price) / (float) $book->old_price) * 100) : null;

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

    // long_description محتوى HTML يحرّره الأدمن. strip_tags ليست تطهيرًا (تُبقي
    // سمات مثل onmouseover)، لذا نمرّره عبر مطهّر DOM حقيقي يُبقي وسومًا آمنة فقط
    // ويزيل كل السمات (on*/style/href/src). قيمة فارغة => نرجع للوصف المختصر.
    $cleanDescription = filled($book->long_description)
        ? \App\Support\HtmlSanitizer::clean($book->long_description)
        : '';
    $safeDescription = $cleanDescription !== '' ? $cleanDescription : null;

    $outcomes = is_array($book->learning_outcomes) ? array_filter($book->learning_outcomes) : [];

    $cartPayload = [
        'id' => $book->id,
        'title' => $book->title,
        'price' => $hasPrice ? number_format((float) $book->price, 0) . ' ' . __('common.currency') : null,
        'url' => route('books.show', $book),
    ];

    $ld = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Book',
        'name' => $book->title,
        'author' => $book->author ?: null,
        'inLanguage' => 'ar',
        'publisher' => ['@type' => 'Organization', 'name' => $book->publisher->name],
        'offers' => $hasPrice ? [
            '@type' => 'Offer',
            'price' => (string) $book->price,
            'priceCurrency' => 'EGP',
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'url' => route('books.show', $book),
        ] : null,
    ]);
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDesc)

@push('head')
    {{-- HEX flags تمنع كسر السياق بـ </script> أو "؛ UNESCAPED_UNICODE يُبقي العربية مقروءة. --}}
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@section('content')
    <div class="wrap" style="padding-block:clamp(20px,4vw,34px)">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span aria-hidden="true">/</span>
            @if ($book->category)
                <a href="{{ route('categories.show', $book->category) }}">{{ $book->category->name }}</a>
                <span aria-hidden="true">/</span>
            @endif
            <span>{{ $book->title }}</span>
        </nav>

        <div class="pdp">
            <div>
                <x-book-cover :book="$book" variant="pdp">
                    @if ($discount)
                        <span class="disc" style="width:56px;height:56px">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
                    @endif
                </x-book-cover>

                @if ($book->images->isNotEmpty())
                    <div class="pdp-thumbs">
                        @foreach ($book->images->take(5) as $img)
                            @php
                                $src = \Illuminate\Support\Str::startsWith($img->path, ['http://', 'https://'])
                                    ? $img->path
                                    : asset('storage/' . ltrim($img->path, '/'));
                            @endphp
                            <img src="{{ $src }}" loading="lazy" decoding="async"
                                alt="{{ $img->alt ?: __('book.gallery_thumb_alt', ['title' => $book->title]) }}">
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <div class="metaline">
                    @if ($book->category)
                        <a class="pill" href="{{ route('categories.show', $book->category) }}">{{ $book->category->name }}</a>
                    @endif
                    @if ($ageText)
                        <span class="pill teal">{{ $ageText }}</span>
                    @endif
                    @if ($book->is_featured)
                        <span class="pill gold">{{ __('book.featured_pill') }}</span>
                    @endif
                </div>

                <h1 style="margin-top:14px">{{ $book->title }}</h1>

                <div style="font-size:13.5px;color:var(--ink-soft);margin-top:6px">
                    @if (filled($book->author)){{ __('book.by_author', ['name' => $book->author]) }}@endif
                    @if (filled($book->author) && filled($book->illustrator)) · @endif
                    @if (filled($book->illustrator)){{ __('book.by_illustrator', ['name' => $book->illustrator]) }}@endif
                </div>

                @if ($book->publisher && $book->publisher->exists)
                    <div style="font-size:13.5px;color:var(--ink-soft);margin-top:4px">{{ __('book.publisher', ['name' => $book->publisher->name]) }}</div>
                @endif

                <div class="pdp-price">
                    @if ($hasPrice)
                        <span class="now">{{ number_format((float) $book->price, 0) }} <span style="font-size:16px">{{ __('common.currency') }}</span></span>
                        @if ($onSale)
                            <span class="old">{{ number_format((float) $book->old_price, 0) }}</span>
                            <span class="save">{{ __('common.save_amount', ['amount' => $saveAmount]) }}</span>
                        @endif
                    @else
                        <span class="na">{{ __('common.price_unavailable') }}</span>
                    @endif
                    @unless ($inStock)
                        <span class="pill" style="background:var(--pink-soft);color:var(--pink)">{{ __('common.out_of_stock') }}</span>
                    @endunless
                </div>

                @if ($safeDescription)
                    <h2 style="font-size:17px;font-weight:800;margin-top:20px">{{ __('book.description_title') }}</h2>
                    <div class="pdp-desc">{!! $safeDescription !!}</div>
                @elseif (filled($book->short_description))
                    <p class="pdp-desc">{{ $book->short_description }}</p>
                @endif

                @if (! empty($outcomes))
                    <h2 style="font-size:17px;font-weight:800;margin-top:20px">{{ __('book.learning_title') }}</h2>
                    <ul class="learn">
                        @foreach ($outcomes as $outcome)
                            <li><span class="ck" aria-hidden="true">✓</span> {{ $outcome }}</li>
                        @endforeach
                    </ul>
                @endif

                <div class="pdp-cta">
                    <button type="button" class="btn btn-primary"
                        @if ($canBuy) @click="$store.cart.add({{ \Illuminate\Support\Js::from($cartPayload) }})" @else disabled @endif>
                        🛒 {{ __('common.add_to_cart') }}
                    </button>
                    <x-wa-button :book="$book" :class="'btn btn-wa'" :label="__('common.order_whatsapp')" />
                </div>

                @if (filled($book->pages_count) || filled($book->isbn))
                    <div style="margin-top:22px;font-size:13.5px;color:var(--ink-soft);display:flex;gap:18px;flex-wrap:wrap">
                        @if (filled($book->pages_count))
                            <span>📄 {{ __('book.pages') }}: {{ $book->pages_count }}</span>
                        @endif
                        @if (filled($book->isbn))
                            <span>🔖 {{ __('book.isbn') }}: {{ $book->isbn }}</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- التقييمات (حقيقية فقط) --}}
        <section class="sec" aria-labelledby="pdp-reviews">
            <h2 class="sec-title" id="pdp-reviews" style="font-size:24px">{{ __('book.reviews_title') }}</h2>
            @if ($reviews->isNotEmpty())
                <div class="reviews" style="margin-top:18px">
                    @foreach ($reviews as $review)
                        <div class="review">
                            <div class="stars" aria-hidden="true">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }}</div>
                            @if (filled($review->title))
                                <strong style="display:block;margin-top:8px">{{ $review->title }}</strong>
                            @endif
                            @if (filled($review->body))
                                <p>{{ $review->body }}</p>
                            @endif
                            <div class="who">
                                <span class="av" aria-hidden="true">{{ mb_substr($review->author_name ?? '؟', 0, 1) }}</span>
                                <div>
                                    <div class="nm">{{ $review->author_name }}</div>
                                    @if ($review->is_verified_purchase)
                                        <div class="role">✅ {{ __('book.verified_purchase') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="sec-desc" style="margin-top:12px">{{ __('book.no_reviews') }}</p>
            @endif
        </section>

        {{-- كتب مشابهة --}}
        @if ($related->isNotEmpty())
            <section class="sec" style="padding-top:6px" aria-labelledby="pdp-related">
                <div class="sec-top" style="text-align:start;max-width:none">
                    <h2 class="sec-title" id="pdp-related" style="font-size:24px">{{ __('book.related_title') }}</h2>
                    <p class="sec-desc">{{ __('book.related_desc') }}</p>
                </div>
                <div class="shelf">
                    @foreach ($related as $rel)
                        <x-book-card :book="$rel" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
