@extends('layouts.app')

{{-- نقش خلفية صفحة الكتاب --}}
@section('body_class', 'pat-scissors-trails')

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
    // معرض بصورة رئيسية متبدّلة: نعرض المسرح عند وجود صورة واحدة على الأقل، وشريط
    // المصغّرات للتنقّل عند وجود أكثر من صورة.
    $hasGallery = count($galleryImages) > 0;
    $showThumbs = count($galleryImages) > 1;

    // JSON-LD للكتاب. يُبنى هنا (بعد المعرض) لأنه يحتاج $galleryImages للحقل image.
    // النوع مزدوج [Product, Book]: أهلية «نتائج المنتجات الغنية» عند Google تقوم على
    // Product، بينما Book يحفظ الدلالة الحقيقية — وschema.org يسمح بتعدّد الأنواع.
    // متروك عمدًا: aggregateRating. المراجعات تُجلب بـ ->take(6) في BookController،
    // فحسابها منها يُنتج reviewCount مقصوصًا ومتوسطًا مغلوطًا — وهو تعارض يستدعي
    // إجراءً يدويًا من Google. تُضاف لاحقًا من $book->avg_rating و reviews_count.
    $ld = array_filter([
        '@context' => 'https://schema.org',
        // Product يُضاف فقط حين يوجد سعر. مواصفة Google تشترط على Product أحد ثلاثة:
        // offers أو review أو aggregateRating — ونحن لا نُصدر الأخيرين. فكتاب بلا
        // سعر (مثل BOOK1) كان سيصير Product بلا offers أي خطأ معلَن في Search Console،
        // بينما Book وحده وسم صحيح تمامًا لأنه ليس نوع نتيجة غنية عند Google.
        '@type' => $hasPrice ? ['Product', 'Book'] : 'Book',
        '@id' => route('books.show', $book) . '#product',
        'url' => route('books.show', $book),
        'name' => $book->title,
        'description' => $metaDesc,
        'image' => array_column($galleryImages, 'src'),
        'author' => $book->author ?: null,
        'inLanguage' => 'ar',
        'isbn' => $book->isbn ?: null,
        'numberOfPages' => $book->pages_count ?: null,
        'brand' => ['@type' => 'Brand', 'name' => $book->publisher->name],
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

    {{-- يقابل مسار الفتات المرئي أسفل الصفحة. القسم اختياري: كتب بلا قسم تُعطي مسارًا من عنصرين. --}}
    <x-breadcrumb-ld :items="array_values(array_filter([
        ['name' => __('nav.home'), 'url' => route('home')],
        $book->category ? ['name' => $book->category->name, 'url' => route('categories.show', $book->category)] : null,
        ['name' => $book->title, 'url' => route('books.show', $book)],
    ]))" />
@endpush

@push('head')
    {{-- عارض الصور المكبّر (Lightbox) + المصغّرات القابلة للنقر. مضمّن كـ <style> لأن
         خادم الاستضافة بلا بناء أصول (npm)؛ يُنشر عبر git مباشرةً (نفس نهج بند 5.2). --}}
    <style>
        .sr-only{ position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
        /* المسرح الرئيسي: صورة متبدّلة (تلقائيًا + بالمصغّرات)؛ التكبير عبر زر مخصّص فقط */
        .pdp-stage{ position:relative; aspect-ratio:.82; border-radius:var(--r-md); overflow:hidden; background:var(--surface); border:1px solid var(--line); box-shadow:var(--shadow); }
        .pdp-stage__img{ position:absolute; inset:0; width:100%; height:100%; object-fit:contain; }
        .pdp-zoombtn{ position:absolute; bottom:12px; inset-inline-end:12px; z-index:3; width:46px; height:46px; border-radius:50%; border:0; background:rgba(255,255,255,.92); color:var(--purple); display:grid; place-items:center; cursor:zoom-in; box-shadow:0 6px 16px -6px rgba(0,0,0,.5); transition:transform .15s, background .15s; -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px); }
        .pdp-zoombtn:hover{ background:#fff; transform:scale(1.06); }
        .pdp-zoombtn:focus-visible{ outline:3px solid var(--purple); outline-offset:2px; }
        .pdp-playbtn{ position:absolute; bottom:12px; inset-inline-start:12px; z-index:3; width:40px; height:40px; border-radius:50%; border:0; background:rgba(255,255,255,.8); color:var(--purple); display:grid; place-items:center; cursor:pointer; box-shadow:0 6px 16px -6px rgba(0,0,0,.45); transition:transform .15s, background .15s; -webkit-backdrop-filter:blur(4px); backdrop-filter:blur(4px); }
        .pdp-playbtn:hover{ background:#fff; transform:scale(1.06); }
        .pdp-playbtn:focus-visible{ outline:3px solid var(--purple); outline-offset:2px; }
        .pdp-thumb{ padding:0; border:0; background:none; line-height:0; cursor:pointer; border-radius:9px; }
        .pdp-thumb img{ display:block; object-fit:contain; background:var(--surface); transition:border-color .15s, transform .15s; }
        .pdp-thumb:hover img{ border-color:var(--purple); transform:translateY(-2px); }
        .pdp-thumb.is-active img{ border-color:var(--purple); }
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
        /* مبدّل عناوين السلسلة */
        .series-switch{ margin-top:20px; padding:16px; border:1px solid var(--line); border-radius:var(--r-md); background:var(--surface); box-shadow:var(--shadow-s); }
        .series-switch__head{ display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; flex-wrap:wrap; }
        .series-switch__label{ font-weight:800; font-size:15px; }
        .series-switch__all{ font-size:12.5px; font-weight:700; color:var(--purple); text-decoration:none; }
        .series-switch__all:hover{ text-decoration:underline; }
        .series-switch__row{ display:flex; gap:10px; overflow-x:auto; padding-bottom:6px; scrollbar-width:thin; }
        .series-item{ flex:0 0 auto; width:88px; text-decoration:none; color:var(--ink); }
        .series-item__cover{ display:grid; place-items:center; width:88px; height:112px; border-radius:10px; overflow:hidden; border:2px solid var(--line); background:var(--purple-soft); transition:border-color .15s, transform .15s; }
        .series-item__cover img{ width:100%; height:100%; object-fit:cover; }
        .series-item__ph{ font-weight:900; font-size:30px; color:var(--purple); }
        .series-item__title{ margin-top:6px; font-size:11.5px; font-weight:700; line-height:1.35; text-align:center; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .series-item:hover .series-item__cover{ border-color:var(--purple); transform:translateY(-2px); }
        .series-item.is-current{ pointer-events:none; }
        .series-item.is-current .series-item__cover{ border-color:var(--purple); box-shadow:0 0 0 3px var(--purple-soft); }
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
            {{-- معرض صور الكتاب: صورة رئيسية تتبدّل تلقائيًا وبالنقر على المصغّرات؛
                 التكبير في وضع الشاشة الكاملة عبر زر التكبير فقط. --}}
            @if ($hasGallery)
            <div x-data="{
                    images: JSON.parse(document.getElementById('pdpGalleryData').textContent),
                    i: 0,
                    lightboxOpen: false,
                    hovered: false,
                    paused: false,
                    inView: true,
                    canHover: false,
                    reduceMotion: false,
                    autoEnabled: false,
                    timer: null,
                    io: null,
                    opener: null,
                    get current() { return this.images[this.i] || { src: '', alt: '' }; },
                    init() {
                        this.canHover = !! (window.matchMedia && window.matchMedia('(hover: hover)').matches);
                        this.reduceMotion = !! (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
                        this.autoEnabled = this.images.length > 1 && ! this.reduceMotion;
                        if (this.autoEnabled && 'IntersectionObserver' in window) {
                            this.io = new IntersectionObserver((es) => { this.inView = es[0].isIntersecting; }, { threshold: 0.25 });
                            this.io.observe(this.$el);
                        }
                        this.start();
                    },
                    start() { this.stop(); if (this.autoEnabled) this.timer = setInterval(() => { if (! this.paused && ! this.hovered && ! this.lightboxOpen && this.inView) this.i = (this.i + 1) % this.images.length; }, 4500); },
                    stop() { if (this.timer) { clearInterval(this.timer); this.timer = null; } },
                    go(n) { this.i = n; this.start(); },
                    togglePause() { this.paused = ! this.paused; },
                    openZoom() { this.opener = document.activeElement; this.lightboxOpen = true; document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; },
                    closeZoom() { if (! this.lightboxOpen) return; this.lightboxOpen = false; document.documentElement.style.overflow = ''; document.body.style.overflow = ''; if (this.opener && this.opener.focus) this.opener.focus(); this.start(); },
                    next() { if (! this.lightboxOpen || this.images.length < 2) return; this.i = (this.i + 1) % this.images.length; },
                    prev() { if (! this.lightboxOpen || this.images.length < 2) return; this.i = (this.i - 1 + this.images.length) % this.images.length; },
                    trap(e) { const f = Array.from(e.currentTarget.querySelectorAll('button')).filter(b => b.offsetParent !== null); if (! f.length) return; const first = f[0], last = f[f.length - 1]; if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); } else if (! e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); } }
                 }">
                <script type="application/json" id="pdpGalleryData">@json($galleryImages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP)</script>

                {{-- منطقة حيّة لقارئ الشاشة: تُعلن «الصورة n من total» داخل وضع التكبير فقط
                     (كي لا تُزعج القارئ أثناء التبديل التلقائي للصورة الرئيسية). --}}
                <div class="sr-only" aria-live="polite"
                    x-text="lightboxOpen ? '{{ __('book.gallery_of') }}'.replace(':n', i + 1).replace(':total', images.length) : ''"></div>

                {{-- الصورة الرئيسية المتبدّلة: تتغيّر تلقائيًا كل بضع ثوانٍ (تتوقّف عند مرور المؤشّر)
                     وعند النقر على مصغّرة؛ زر التكبير وحده يفتح وضع الشاشة الكاملة. --}}
                <div class="pdp-stage" @mouseenter="hovered = canHover" @mouseleave="hovered = false">
                    {{-- src ثابت (يراه ماسح التحميل المسبق) + :src للتبديل؛ fetchpriority لتحسين LCP على الموبايل --}}
                    <img class="pdp-stage__img" src="{{ $galleryImages[0]['src'] }}" :src="current.src"
                        alt="{{ $galleryImages[0]['alt'] }}" :alt="current.alt" fetchpriority="high"
                        x-init="$watch('i', () => { if (! reduceMotion) $el.animate([{ opacity: .4 }, { opacity: 1 }], { duration: 320, easing: 'ease-out' }); })">

                    @if ($discount)
                        <span class="disc" style="width:56px;height:56px;pointer-events:none">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
                    @endif

                    {{-- زر إيقاف/تشغيل التبديل التلقائي (يظهر فقط عند تفعيل التبديل) — متطلّب وصولية WCAG 2.2.2 --}}
                    @if ($showThumbs)
                        <button type="button" class="pdp-playbtn" x-show="autoEnabled" x-cloak @click="togglePause()"
                            :aria-label="paused ? '{{ __('book.gallery_play') }}' : '{{ __('book.gallery_pause') }}'"
                            :aria-pressed="paused ? 'true' : 'false'">
                            <span x-show="! paused"><x-ui-icon name="pause" :size="18" /></span>
                            <span x-show="paused" x-cloak><x-ui-icon name="play" :size="18" /></span>
                        </button>
                    @endif

                    <button type="button" class="pdp-zoombtn" @click="openZoom()" aria-label="{{ __('book.zoom_hint') }}">
                        <x-ui-icon name="search" :size="20" />
                    </button>
                </div>

                @if ($showThumbs)
                    <div class="pdp-thumbs">
                        @foreach ($galleryImages as $gi => $img)
                            <button type="button" class="pdp-thumb" @click="go({{ $gi }})"
                                :class="{ 'is-active': i === {{ $gi }} }" :aria-current="i === {{ $gi }}"
                                aria-label="{{ __('book.gallery_view_alt', ['n' => $gi + 1]) }}">
                                <img src="{{ $img['src'] }}" loading="lazy" decoding="async" alt="{{ $img['alt'] }}">
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- العارض المكبّر — يُنقل إلى نهاية body لتفادي قصّه بحدود العمود --}}
                <template x-teleport="body">
                    <div class="lightbox" x-show="lightboxOpen" x-cloak x-transition.opacity
                        @keydown.escape.window="closeZoom()"
                        @keydown.arrow-left.window="next()"
                        @keydown.arrow-right.window="prev()"
                        @keydown.tab="trap($event)"
                        @click="closeZoom()" role="dialog" aria-modal="true"
                        aria-label="{{ __('book.gallery_dialog') }}">

                        <img class="lightbox__img" :src="current.src" :alt="current.alt" @click.stop>

                        <button type="button" class="lightbox__btn lightbox__close" @click.stop="closeZoom()"
                            x-init="$watch('lightboxOpen', v => { if (v) $nextTick(() => $el.focus()); })"
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
            @else
                {{-- لا صور للكتاب: نعرض بطاقة الغلاف البديلة (بلا معرض) --}}
                <x-book-cover :book="$book" variant="pdp">
                    @if ($discount)
                        <span class="disc" style="width:56px;height:56px">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
                    @endif
                </x-book-cover>
            @endif

            <div>
                <div class="metaline">
                    @if ($book->category)
                        <a class="pill" href="{{ route('categories.show', $book->category) }}">{{ $book->category->name }}</a>
                    @endif
                    {{-- أقسام إضافية (متعدّدة الأقسام) — نتجاوز القسم الرئيسي كي لا يتكرّر --}}
                    @foreach ($book->categories as $extraCategory)
                        @if ((int) $extraCategory->id !== (int) $book->category_id)
                            <a class="pill" href="{{ route('categories.show', $extraCategory) }}">{{ $extraCategory->name }}</a>
                        @endif
                    @endforeach
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

                {{-- مبدّل عناوين السلسلة: كل عنوان كتاب مستقل بغلافه وسعره؛ النقر ينقل لصفحته --}}
                @if ($seriesBooks->count() > 1)
                    <div class="series-switch">
                        <div class="series-switch__head">
                            <span class="series-switch__label">{{ __('book.series_titles') }}</span>
                            @if ($book->series)
                                <a class="series-switch__all" href="{{ route('series.show', $book->series) }}"
                                    aria-label="{{ __('book.series_browse') }}">{{ $book->series->name }} <span aria-hidden="true">←</span></a>
                            @endif
                        </div>
                        <div class="series-switch__row">
                            @foreach ($seriesBooks as $sb)
                                @php
                                    $isCurrent = (int) $sb->id === (int) $book->id;
                                    $sbCover = filled($sb->cover_image)
                                        ? (\Illuminate\Support\Str::startsWith($sb->cover_image, ['http://', 'https://'])
                                            ? $sb->cover_image
                                            : asset('storage/' . ltrim($sb->cover_image, '/')))
                                        : null;
                                @endphp
                                <a class="series-item {{ $isCurrent ? 'is-current' : '' }}"
                                    @if ($isCurrent) aria-current="true" @else href="{{ route('books.show', $sb) }}" @endif
                                    title="{{ $sb->title }}">
                                    <span class="series-item__cover">
                                        @if ($sbCover)
                                            <img src="{{ $sbCover }}" loading="lazy" decoding="async" alt="{{ $isCurrent ? '' : $sb->title }}">
                                        @else
                                            <span class="series-item__ph" aria-hidden="true">{{ mb_substr($sb->title, 0, 1) }}</span>
                                        @endif
                                    </span>
                                    <span class="series-item__title">{{ $sb->title }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

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
