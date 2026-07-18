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

    // معرض الصور القابل للتكبير: الغلاف (فهرس 0) ثم الصور الإضافية الحقيقية فقط.
    // الغلاف مخزَّن مرّتين: عمود cover_image + صف is_cover في book_images. لذا نبني
    // القائمة من عمود الغلاف ثم نضمّ بقية الصور مع استبعاد صف الغلاف (is_cover) وأي
    // صورة يطابق مصدرها الغلاف، حتى لا يتكرر الغلاف ولا تُحجب صورة حقيقية بلا داعٍ.
    $resolveImageUrl = static fn (string $path): string => \Illuminate\Support\Str::startsWith($path, ['http://', 'https://'])
        ? $path
        : asset('storage/' . ltrim($path, '/'));

    $coverIsImage = filled($book->cover_image);
    $coverSrc = $coverIsImage ? $resolveImageUrl($book->cover_image) : null;

    $galleryImages = [];
    if ($coverIsImage) {
        $galleryImages[] = ['src' => $coverSrc, 'alt' => $book->title];
    }
    // نستبعد صف الغلاف فقط عندما يوجد غلاف في العمود فعلاً؛ وإلا (عمود فارغ + صف
    // is_cover) نُبقيه ليظهر في المعرض بدل أن يختفي تمامًا.
    $extraImages = $book->images
        ->reject(fn ($img) => $coverIsImage && ($img->is_cover || $resolveImageUrl($img->path) === $coverSrc));
    foreach ($extraImages as $img) {
        $galleryImages[] = [
            'src' => $resolveImageUrl($img->path),
            'alt' => $img->alt ?: __('book.gallery_thumb_alt', ['title' => $book->title]),
        ];
    }
    // شريط المصغّرات: عند وجود غلاف قابل للتكبير نعرضه فقط لو توجد صورة إضافية (>1)؛
    // أمّا لو لا غلاف حقيقي (الكبير مجرّد بديل غير قابل للنقر) فأي صورة تحتاج مصغّرة لتُرى.
    $showThumbs = $coverIsImage ? count($galleryImages) > 1 : count($galleryImages) >= 1;
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDesc)

@push('head')
    {{-- HEX flags تمنع كسر السياق بـ </script> أو "؛ UNESCAPED_UNICODE يُبقي العربية مقروءة. --}}
    <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush

@push('head')
    {{-- عارض الصور المكبّر (Lightbox) + المصغّرات القابلة للنقر. مضمّن كـ <style> لأن
         خادم الاستضافة بلا بناء أصول (npm)؛ يُنشر عبر git مباشرةً (نفس نهج بند 5.2). --}}
    <style>
        .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        .pdp-zoombtn{ position:absolute; inset:0; z-index:2; margin:0; padding:0; border:0; background:none; cursor:zoom-in; }
        .pdp-zoombtn:focus-visible{ outline:3px solid #fff; outline-offset:-4px; border-radius:var(--r-md); }
        .pdp-zoombadge{ position:absolute; bottom:12px; inset-inline-start:12px; z-index:3; width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.92); color:var(--purple); display:grid; place-items:center; box-shadow:0 4px 14px -4px rgba(0,0,0,.45); -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px); }
        .pdp-thumb{ padding:0; border:0; background:none; line-height:0; cursor:zoom-in; border-radius:9px; }
        .pdp-thumb img{ display:block; transition:border-color .15s, transform .15s; }
        .pdp-thumb:hover img{ border-color:var(--purple); transform:translateY(-2px); }
        .pdp-thumb:focus-visible{ outline:3px solid var(--purple); outline-offset:2px; }
        [x-cloak]{ display:none !important; }
        .lightbox{ position:fixed; inset:0; z-index:1200; background:rgba(18,9,28,.94); display:grid; place-items:center; padding:clamp(14px,4vw,48px); }
        .lightbox__img{ max-width:100%; max-height:100%; object-fit:contain; border-radius:12px; background:#fff; box-shadow:0 30px 80px -30px rgba(0,0,0,.8); }
        .lightbox__btn{ position:absolute; width:48px; height:48px; border-radius:50%; border:none; background:rgba(255,255,255,.16); color:#fff; display:grid; place-items:center; cursor:pointer; transition:background .15s; -webkit-backdrop-filter:blur(6px); backdrop-filter:blur(6px); }
        .lightbox__btn:hover{ background:rgba(255,255,255,.32); }
        .lightbox__btn:focus-visible{ outline:3px solid #fff; outline-offset:2px; }
        .lightbox__close{ top:16px; inset-inline-end:16px; }
        .lightbox__prev{ top:50%; transform:translateY(-50%); inset-inline-start:14px; }
        .lightbox__next{ top:50%; transform:translateY(-50%); inset-inline-end:14px; }
        .lightbox__count{ position:absolute; bottom:18px; left:50%; transform:translateX(-50%); color:#fff; font-weight:700; font-size:14px; background:rgba(0,0,0,.45); padding:6px 16px; border-radius:99px; font-variant-numeric:tabular-nums; direction:ltr; }
        @media (max-width:600px){ .lightbox__btn{ width:42px; height:42px; } .lightbox__prev{ inset-inline-start:8px; } .lightbox__next{ inset-inline-end:8px; } }
        @media (prefers-reduced-motion:reduce){ .pdp-thumb img{ transition:none; } }
    </style>
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
            {{-- معرض صور الكتاب: نقرة على الغلاف أو أي مصغّرة تفتح عارضًا مكبّرًا (Lightbox) --}}
            <div x-data="{
                    images: JSON.parse(document.getElementById('pdpGalleryData').textContent),
                    i: null,
                    isOpen: false,
                    opener: null,
                    get current() { return (this.i === null ? null : this.images[this.i]) || { src: '', alt: '' }; },
                    show(n, opener) { this.opener = opener || document.activeElement; this.i = n; this.isOpen = true; document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; },
                    close() { this.isOpen = false; document.documentElement.style.overflow = ''; document.body.style.overflow = ''; if (this.opener && this.opener.focus) this.opener.focus(); },
                    next() { if (! this.isOpen || this.images.length < 2) return; this.i = (this.i + 1) % this.images.length; },
                    prev() { if (! this.isOpen || this.images.length < 2) return; this.i = (this.i - 1 + this.images.length) % this.images.length; },
                    trap(e) { const f = Array.from(e.currentTarget.querySelectorAll('button')).filter(b => b.offsetParent !== null); if (! f.length) return; const first = f[0], last = f[f.length - 1]; if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (! e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } }
                 }">
                <script type="application/json" id="pdpGalleryData">@json($galleryImages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)</script>

                {{-- منطقة حيّة لقارئ الشاشة: خارج الحاوية المخفية كي تُعلن «الصورة n من total»
                     عند الفتح وعند كل تنقّل (لو كانت داخل x-show تظهر مملوءة فلا تُنطق أوّل مرة). --}}
                <div class="sr-only" aria-live="polite"
                    x-text="isOpen && i !== null ? '{{ __('book.gallery_of') }}'.replace(':n', i + 1).replace(':total', images.length) : ''"></div>

                <x-book-cover :book="$book" variant="pdp">
                    {{-- شارة الخصم تبقى خارج زر التكبير كي يقرأها قارئ الشاشة (لا تُقصّ من شجرة الوصول) --}}
                    @if ($discount)
                        <span class="disc" style="width:56px;height:56px;pointer-events:none">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
                    @endif
                    {{-- زر شفّاف يغطّي الغلاف بالكامل: نقرة في أي مكان تفتح العارض المكبّر (زر أصلي = لوحة مفاتيح سليمة) --}}
                    @if ($coverIsImage)
                        <button type="button" class="pdp-zoombtn" @click="show(0, $event.currentTarget)" aria-label="{{ __('book.zoom_hint') }}">
                            <span class="pdp-zoombadge" aria-hidden="true"><x-ui-icon name="search" :size="18" /></span>
                        </button>
                    @endif
                </x-book-cover>

                @if ($showThumbs)
                    <div class="pdp-thumbs">
                        @foreach ($galleryImages as $gi => $img)
                            <button type="button" class="pdp-thumb" @click="show({{ $gi }}, $event.currentTarget)"
                                aria-label="{{ __('book.gallery_view_alt', ['n' => $gi + 1]) }}">
                                <img src="{{ $img['src'] }}" loading="lazy" decoding="async" alt="{{ $img['alt'] }}">
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- العارض المكبّر — يُنقل إلى نهاية body لتفادي قصّه بحدود العمود --}}
                <template x-teleport="body">
                    <div class="lightbox" x-show="isOpen" x-cloak x-transition.opacity
                        @keydown.escape.window="close()"
                        @keydown.arrow-left.window="next()"
                        @keydown.arrow-right.window="prev()"
                        @keydown.tab="trap($event)"
                        @click="close()" role="dialog" aria-modal="true"
                        aria-label="{{ __('book.gallery_dialog') }}">

                        <img class="lightbox__img" :src="current.src" :alt="current.alt" @click.stop>

                        <button type="button" class="lightbox__btn lightbox__close" @click.stop="close()"
                            x-init="$watch('isOpen', v => { if (v) $nextTick(() => $el.focus()); })"
                            aria-label="{{ __('common.close') }}"><x-ui-icon name="close" :size="24" /></button>

                        <template x-if="images.length > 1">
                            <div style="display:contents">
                                <button type="button" class="lightbox__btn lightbox__prev" @click.stop="prev()"
                                    aria-label="{{ __('book.gallery_prev') }}">
                                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
                                </button>
                                <button type="button" class="lightbox__btn lightbox__next" @click.stop="next()"
                                    aria-label="{{ __('book.gallery_next') }}">
                                    <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
                                </button>
                                <div class="lightbox__count" aria-hidden="true"><span x-text="i + 1"></span> / <span x-text="images.length"></span></div>
                            </div>
                        </template>
                    </div>
                </template>
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
