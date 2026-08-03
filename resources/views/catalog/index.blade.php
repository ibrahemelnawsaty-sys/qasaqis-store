@extends('layouts.app')


@section('title', $heading . ' — ' . __('common.brand'))

@push('head')
    {{-- تحسين شريط أدوات الكتب على الجوال: القسم/الفئة العمرية/الترتيب قوائمُ كاملة
         العرض بعناوين ظاهرة صغيرة (تمييز واضح)، وزرّ الفلاتر ممتدّ أعلاها — أنظف وأوضح
         من صفٍّ مزدحم بلا عناوين. لا يمسّ سطح المكتب. --}}
    <style>
        @media (max-width: 640px) {
            .catalog-toolbar { align-items: stretch; gap: 12px; }
            .catalog-count { align-self: flex-start; }
            /* القسم / الفئة العمرية / الترتيب: قوائم كاملة العرض بعناوين صغيرة ظاهرة */
            .catalog-controls { width: 100%; display: flex !important; flex-direction: column; gap: 10px; }
            .catalog-controls > .filters-toggle { justify-content: center; height: 46px; font-weight: 700; }
            .catalog-control { width: 100%; flex-direction: column; align-items: stretch !important; gap: 4px !important; }
            .catalog-control .hide-mobile { display: block !important; font-size: 11.5px; font-weight: 800; opacity: .72; padding-inline-start: 6px; }
            .catalog-control .sort-select { width: 100%; height: 46px; padding-inline: 12px; }
        }
    </style>
@endpush

@php
    // وصف ميتا فريد لكل سياق. كانت كل صفحات الأقسام والتصفّح والسلاسل ترث الوصف
    // الافتراضي الواحد من التخطيط (common.tagline)، فتتنافس على نفس المقتطف في النتائج.
    // الأولوية لكل سياق: تجاوز الأدمن (seo_meta) ← المشتقّ من الكيان نفسه عبر
    // SeoDefaults ← نصّ عام. القسم والسلسلة لكلٍّ اشتقاقه الخاص من اسمه/وصفه؛ صفحتا
    // التصفّح والبحث لا كيان لهما فترجعان للنصّ العام. (كان $seoSeries يُحسب ولا يُستخدم.)
    $seoCategory = $category ?? null;
    $seoSeries = $series ?? null;
    $seoEntity = $seoCategory ?? $seoSeries;
    $catalogMetaDescription = $seoEntity?->seo?->meta_description
        ?: ($seoEntity
            ? \App\Support\Seo\SeoDefaults::description($seoEntity)
            : __('catalog.meta_description', ['context' => $heading, 'brand' => __('common.brand')]));

    // canonical صريح: url()->current() يحذف الـ query string فيوحّد كل الفلاتر
    // والفرز والتصفيح على رابط واحد — وهو المطلوب، عدا /offers الذي يستحق رابطه.
    $catalogCanonical = match (true) {
        $seoCategory !== null => route('categories.show', $seoCategory),
        $seoSeries !== null => route('series.show', $seoSeries),
        request()->routeIs('books.offers') => route('books.offers'),
        request()->routeIs('books.new') => route('books.new'),
        request()->routeIs('search') => null,
        default => route('books.index'),
    };

    // تجاوزات الأدمن للقسم/السلسلة (seo_meta): canonical/robots/og — كانت معروضة في الأدمن
    // لكن تُهمَل للقسم/السلسلة خلافًا لصفحات CMS. الرجوع للافتراضي عند الغياب.
    $entitySeo = $seoEntity?->seo;
    if (filled($entitySeo?->canonical_url)) {
        $catalogCanonical = $entitySeo->canonical_url;
    }
    $catalogRobots = $entitySeo?->robots ?: null;
    $catalogOgTitle = $entitySeo?->og_title ?: ($heading.' — '.__('common.brand'));
    $catalogOgDescription = $entitySeo?->og_description ?: $catalogMetaDescription;
    $catalogOgImage = filled($entitySeo?->og_image_path)
        ? (\Illuminate\Support\Str::startsWith($entitySeo->og_image_path, ['http://', 'https://'])
            ? $entitySeo->og_image_path
            : asset('storage/'.ltrim($entitySeo->og_image_path, '/')))
        : null;
@endphp

@section('meta_description', $catalogMetaDescription)

@if ($catalogCanonical !== null)
    @section('seo_canonical', $catalogCanonical)
@endif

{{-- نتائج البحث لا تُفهرس: محتوى مولَّد بلا قيمة مستقلة، وقد يولّد روابط لا نهائية. --}}
@if (request()->routeIs('search'))
    @section('seo_robots', 'noindex, follow')
@endif

{{-- تجاوز robots للقسم/السلسلة (مثل noindex قسم بعينه). البحث مستثنى أعلاه (لا كيان له،
     فـ$catalogRobots فارغ) فلا يتعارض القسمان. --}}
@if ($catalogRobots)
    @section('seo_robots', $catalogRobots)
@endif

@section('og_title', $catalogOgTitle)
@section('og_description', $catalogOgDescription)

@if ($catalogOgImage)
    @section('og_image', $catalogOgImage)
@endif

{{-- يقابل مسار الفتات المرئي. صفحات البحث مستثناة: لا تُفهرس فلا قيمة لوسمها. --}}
@if ($catalogCanonical !== null)
    @push('head')
        <x-breadcrumb-ld :items="[
            ['name' => __('nav.home'), 'url' => route('home')],
            ['name' => $heading, 'url' => $catalogCanonical],
        ]" />
    @endpush
@endif

{{-- ItemList للصفحة: يربط Google كتب الصفحة كقائمة مرتّبة (أهلية عرض القوائم/الكاروسيل).
     يُصدَر فقط على الصفحات المفهرَسة (canonical صريح) وحين توجد كتب — صفحة البحث مستثناة.
     نُخرج url + name فقط (صفحة ملخّص): التفاصيل الغنية في صفحة الكتاب نفسها. --}}
@if ($catalogCanonical !== null && $books->isNotEmpty())
    @push('head')
        @php
            $itemListLd = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'itemListElement' => collect($books->items())->map(fn ($b, $i) => [
                    '@type' => 'ListItem',
                    'position' => ($books->firstItem() ?? 1) + $i,
                    'url' => route('books.show', $b),
                    'name' => $b->title,
                ])->all(),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($itemListLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endpush
@endif

@section('content')
    @php
        // صفحة السلسلة: قائمة عناوين مرتّبة (بلا فلاتر/فرز — الترتيب ثابت حسب السلسلة).
        $series = $series ?? null;
        $plainList = $series !== null;

        // مسار النموذج ووجهة «مسح الكل» بحسب سياق الصفحة (قسم / سلسلة / بحث / كل الكتب)
        if ($category) {
            $formAction = route('categories.show', $category);
            $clearUrl = route('categories.show', $category);
        } elseif ($series) {
            $formAction = route('series.show', $series);
            $clearUrl = route('series.show', $series);
        } elseif (! is_null($searchTerm)) {
            $formAction = route('search');
            $clearUrl = route('search', ['q' => $searchTerm]);
        } elseif (request()->routeIs('books.offers')) {
            // /offers صار صفحة 200 قائمة بذاتها (كان تحويلًا إلى /books?sale=1).
            // بلا هذا الفرع يُرسل النموذج إلى /books فيغادر الزائر صفحة العروض
            // ويسقط فلتر الخصم عند أول فلترة أو فرز.
            $formAction = route('books.offers');
            $clearUrl = route('books.offers');
        } elseif (request()->routeIs('books.new')) {
            // /new صفحة 200 قائمة بذاتها بفلتر «الجديد» المفروض — النموذج يبقى عليها
            // كي لا يسقط الفلتر عند أول فلترة/فرز (نفس علّة /offers).
            $formAction = route('books.new');
            $clearUrl = route('books.new');
        } else {
            $formAction = route('books.index');
            $clearUrl = route('books.index');
        }

        // «المُقترَح» (الترتيب اليدويّ) هو الافتراضي المُحدَّد في صفحة القسم و/books معًا،
        // فأي فلترة/إرسال للنموذج تُبقيه (لا تُرسل sort=newest فتُلغي الترتيب اليدويّ).
        // صفحتا العروض/وصل حديثًا تبقيان على «الأحدث».
        $useCurated = $category !== null || request()->routeIs('books.index');
        $sortOptions = $useCurated
            ? ['curated', 'newest', 'price_asc', 'price_desc', 'rating', 'popular']
            : ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];
        $currentSort = request('sort', $useCurated ? 'curated' : 'newest');
    @endphp

    <div class="wrap" style="padding-block:clamp(20px,4vw,34px)">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $heading }}</span>
        </nav>

        <div class="sec-top" style="text-align:start;margin:0 0 20px;max-width:none">
            <h1 class="sec-title" style="margin-top:0">{{ $heading }}</h1>
            @if ($category && filled($category->description))
                <p class="sec-desc">{{ $category->description }}</p>
            @elseif (request()->routeIs('books.offers'))
                {{-- نصّ تعريفيّ فريد لصفحة العروض كي لا تكون نسخة مطابقة من /books
                     (يمنع «Crawled – currently not indexed» بإعطائها قيمة مستقلّة). --}}
                <p class="sec-desc">{{ __('catalog.offers_intro') }}</p>
            @elseif (request()->routeIs('books.new'))
                {{-- نصّ تعريفيّ فريد لصفحة «وصل حديثًا» (قيمة فهرسة مستقلّة عن /books). --}}
                <p class="sec-desc">{{ __('catalog.new_intro') }}</p>
            @endif
        </div>

        <form method="get" action="{{ $formAction }}" x-data="{ sheet: false }">
            @if (! is_null($searchTerm) && $searchTerm !== '')
                <input type="hidden" name="q" value="{{ $searchTerm }}">
            @endif

            {{-- القسم يُختار الآن من قائمة الشريط (ملاحة، لا حقل نموذج)؛ نحمله مخفيًّا كي
                 يبقى محفوظًا عند تغيير الترتيب أو تطبيق فلاتر اللوحة (بديل حقول cat[] المحذوفة). --}}
            @foreach (array_map('strval', (array) request('cat', [])) as $cid)
                <input type="hidden" name="cat[]" value="{{ $cid }}">
            @endforeach

            <div class="catalog-toolbar">
                <span class="catalog-count">{{ trans_choice('catalog.results_count', $books->total(), ['count' => $books->total()]) }}</span>

                @unless ($plainList)
                    {{-- flex-wrap كي تلتفّ الحبّات (فلاتر/عمر/ترتيب) بدل الفيض أفقيًا على
                         الهواتف الضيّقة (~360px) — جمهور بشبكة/أجهزة ضعيفة (بند 1.6). --}}
                    <div class="catalog-controls" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <button type="button" class="btn btn-ghost filters-toggle" @click="sheet = true">
                            🔽 {{ __('catalog.filters_open') }}
                        </button>

                        {{-- الأقسام بجانب الفئة العمرية: قائمة ملاحة مستقلّة (بلا name فلا
                             تتعارض مع النموذج). كل خيار رابطٌ يحفظ باقي الفلاتر ويضبط القسم
                             ويعيد الترقيم للأول. حلّت محلّ فلتر القسم في اللوحة الجانبية. --}}
                        @if (! $category)
                            @php $catSel = array_map('strval', (array) request('cat', [])); @endphp
                            <label class="catalog-control" style="display:flex;align-items:center;gap:6px">
                                <span class="hide-mobile" style="font-size:13px;color:var(--ink-soft)">{{ __('catalog.facet_category') }}</span>
                                <select class="sort-select" aria-label="{{ __('catalog.facet_category') }}"
                                    onchange="if (this.value) window.location.href = this.value">
                                    @if (count($catSel) > 1)
                                        <option selected disabled hidden>{{ __('catalog.category_multiple') }}</option>
                                    @endif
                                    <option value="{{ request()->fullUrlWithQuery(['cat' => null, 'page' => null]) }}" @selected($catSel === [])>{{ __('catalog.category_all') }}</option>
                                    @foreach ($categories as $cat)
                                        @continue($cat->books_count === 0)
                                        <option value="{{ request()->fullUrlWithQuery(['cat' => [$cat->id], 'page' => null]) }}"
                                            @selected(count($catSel) === 1 && $catSel[0] === (string) $cat->id)>{{ $cat->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        {{-- الفئة العمرية: قائمة ملاحة مستقلّة (age[]=… يضبط عنصرًا واحدًا). --}}
                        @php $ageSel = array_values((array) request('age', [])); @endphp
                        <label class="catalog-control" style="display:flex;align-items:center;gap:6px">
                            <span class="hide-mobile" style="font-size:13px;color:var(--ink-soft)">{{ __('catalog.facet_age') }}</span>
                            {{-- age يُمرَّر مصفوفةً (age[]=…) مطابقةً لقاعدة التحقّق ولفلتر
                                 age[] في اللوحة الجانبية؛ القائمة أحادية فتضبط عنصرًا واحدًا. --}}
                            <select class="sort-select" aria-label="{{ __('catalog.facet_age') }}"
                                onchange="if (this.value) window.location.href = this.value">
                                {{-- عند اختيار أعمار متعددة من اللوحة الجانبية، القائمة الأحادية
                                     لا تُمثّلها؛ نُظهر نائبًا صادقًا بدل أن يبدو «كل الأعمار» مختارًا. --}}
                                @if (count($ageSel) > 1)
                                    <option selected disabled hidden>{{ __('catalog.age_multiple') }}</option>
                                @endif
                                <option value="{{ request()->fullUrlWithQuery(['age' => null, 'page' => null]) }}" @selected($ageSel === [])>{{ __('catalog.age_all') }}</option>
                                @foreach ($ageOptions as $opt)
                                    <option value="{{ request()->fullUrlWithQuery(['age' => [$opt['value']], 'page' => null]) }}"
                                        @selected(count($ageSel) === 1 && $ageSel[0] === $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="catalog-control" style="display:flex;align-items:center;gap:6px">
                            <span class="hide-mobile" style="font-size:13px;color:var(--ink-soft)">{{ __('catalog.sort_label') }}</span>
                            <select name="sort" class="sort-select" onchange="this.form.requestSubmit()"
                                aria-label="{{ __('catalog.sort_label') }}">
                                @foreach ($sortOptions as $opt)
                                    <option value="{{ $opt }}" @selected($currentSort === $opt)>{{ __('catalog.sort.' . $opt) }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endunless
            </div>

            <div class="catalog-grid" @if ($plainList) style="grid-template-columns:1fr" @endif>
                @unless ($plainList)
                    {{-- backdrop للـ bottom-sheet على الموبايل --}}
                    <div class="drawer-backdrop" x-show="sheet" x-cloak @click="sheet = false" style="z-index:74"></div>

                    <aside class="filters as-sidebar" :class="{ 'sheet-open': sheet }" aria-label="{{ __('catalog.filters') }}">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                            <strong>{{ __('catalog.filters') }}</strong>
                            <a href="{{ $clearUrl }}" style="font-size:13px;color:var(--purple);text-decoration:none">{{ __('catalog.filters_clear') }}</a>
                        </div>
                        @include('partials.filters', [
                            'categories' => $categories,
                            'publishers' => $publishers,
                            'ageOptions' => $ageOptions,
                            'category' => $category,
                        ])
                        <button type="button" class="btn btn-ghost btn-block filters-toggle" style="margin-top:8px"
                            @click="sheet = false">{{ __('common.close') }}</button>
                    </aside>
                @endunless

                <div>
                    @if ($books->isNotEmpty())
                        <div class="shelf">
                            @foreach ($books as $book)
                                <x-book-card :book="$book" />
                            @endforeach
                        </div>

                        <div class="pagination-wrap">
                            {{ $books->onEachSide(1)->links() }}
                        </div>
                    @else
                        <div class="empty-state">
                            <div class="em" aria-hidden="true">🔍</div>
                            @if (! is_null($searchTerm) && $searchTerm !== '')
                                <h2 class="sec-title" style="font-size:22px">{{ __('search.no_results') }}</h2>
                                <ul style="list-style:none;color:var(--ink-soft);margin-top:10px;line-height:2">
                                    @foreach (__('search.no_results_hints') as $hint)
                                        <li>• {{ $hint }}</li>
                                    @endforeach
                                </ul>
                            @elseif (request()->routeIs('books.offers'))
                                <h2 class="sec-title" style="font-size:22px">{{ __('catalog.offers_empty_title') }}</h2>
                                <p class="sec-desc">{{ __('catalog.offers_empty_hint') }}</p>
                            @elseif (request()->routeIs('books.new'))
                                <h2 class="sec-title" style="font-size:22px">{{ __('catalog.new_empty_title') }}</h2>
                                <p class="sec-desc">{{ __('catalog.new_empty_hint') }}</p>
                            @else
                                <h2 class="sec-title" style="font-size:22px">{{ __('catalog.empty_title') }}</h2>
                                <p class="sec-desc">{{ __('catalog.empty_hint') }}</p>
                            @endif
                            <div style="margin-top:18px">
                                <a class="btn btn-ghost" href="{{ route('books.index') }}">{{ __('nav.all_books') }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </form>

        {{-- اقتراحات بديلة عند غياب نتائج البحث (كتب حقيقية) --}}
        @if (isset($fallbackBooks) && $fallbackBooks->isNotEmpty() && $books->isEmpty())
            <section class="sec" style="padding-top:10px">
                <div class="sec-top" style="text-align:start;max-width:none">
                    <h2 class="sec-title" style="font-size:22px">{{ __('search.fallback_title') }}</h2>
                </div>
                <div class="shelf">
                    @foreach ($fallbackBooks as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
