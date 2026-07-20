@extends('layouts.app')


@section('title', $heading . ' — ' . __('common.brand'))

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
        request()->routeIs('search') => null,
        default => route('books.index'),
    };
@endphp

@section('meta_description', $catalogMetaDescription)

@if ($catalogCanonical !== null)
    @section('seo_canonical', $catalogCanonical)
@endif

{{-- نتائج البحث لا تُفهرس: محتوى مولَّد بلا قيمة مستقلة، وقد يولّد روابط لا نهائية. --}}
@if (request()->routeIs('search'))
    @section('seo_robots', 'noindex, follow')
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
        } else {
            $formAction = route('books.index');
            $clearUrl = route('books.index');
        }

        $sortOptions = ['newest', 'price_asc', 'price_desc', 'rating', 'popular'];
        $currentSort = request('sort', 'newest');
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
            @endif
        </div>

        <form method="get" action="{{ $formAction }}" x-data="{ sheet: false }">
            @if (! is_null($searchTerm) && $searchTerm !== '')
                <input type="hidden" name="q" value="{{ $searchTerm }}">
            @endif

            <div class="catalog-toolbar">
                <span class="catalog-count">{{ trans_choice('catalog.results_count', $books->total(), ['count' => $books->total()]) }}</span>

                @unless ($plainList)
                    <div style="display:flex;gap:8px;align-items:center">
                        <button type="button" class="btn btn-ghost filters-toggle" @click="sheet = true">
                            🔽 {{ __('catalog.filters_open') }}
                        </button>
                        {{-- الفئة العمرية بجانب الترتيب: قائمة ملاحة مستقلّة (بلا name فلا
                             تُرسَل مع النموذج ولا تتعارض مع age[] في لوحة الفلاتر). كل خيار
                             رابطٌ يحفظ باقي الفلاتر ويضبط age لقيمة واحدة ويعيد الترقيم للأول. --}}
                        @php $ageSel = array_values((array) request('age', [])); @endphp
                        <label style="display:flex;align-items:center;gap:6px">
                            <span class="hide-mobile" style="font-size:13px;color:var(--ink-soft)">{{ __('catalog.facet_age') }}</span>
                            {{-- age يُمرَّر مصفوفةً (age[]=…) مطابقةً لقاعدة التحقّق ولفلتر
                                 age[] في اللوحة الجانبية؛ القائمة أحادية فتضبط عنصرًا واحدًا. --}}
                            <select class="sort-select" aria-label="{{ __('catalog.facet_age') }}"
                                onchange="if (this.value) window.location.href = this.value">
                                <option value="{{ request()->fullUrlWithQuery(['age' => null, 'page' => null]) }}" @selected($ageSel === [])>{{ __('catalog.age_all') }}</option>
                                @foreach ($ageOptions as $opt)
                                    <option value="{{ request()->fullUrlWithQuery(['age' => [$opt['value']], 'page' => null]) }}"
                                        @selected(count($ageSel) === 1 && $ageSel[0] === $opt['value'])>{{ $opt['label'] }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label style="display:flex;align-items:center;gap:6px">
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
