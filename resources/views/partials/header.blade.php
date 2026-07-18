@php
    // روابط قائمة الهيدر المُدارة من الـ CMS (Menu location=header). تُجلب مرة واحدة
    // مع تحميل مسبق (eager) للعناصر وربطها المتعدد لتفادي N+1. rescue() تُبقي الهيدر
    // يعمل قبل تشغيل الهجرات (قائمة فارغة). عند غياب القائمة تبقى الروابط الافتراضية.
    $resolveMenuUrl = static function ($item): ?string {
        // الأدمن يملأ url مباشرةً لنوع «رابط»؛ للأنواع الأخرى يُشتق من الهدف المرتبط
        // عبر المسارات الموجودة فعليًا فقط (دار النشر بلا مسار متجر بعد => تُتجاهل).
        if (filled($item->url)) {
            return $item->url;
        }

        $target = $item->linkable;

        if ($target !== null) {
            return match ($item->link_type) {
                'page' => route('pages.show', $target),
                'category' => route('categories.show', $target),
                'product' => route('books.show', $target),
                default => null,
            };
        }

        return null;
    };

    $headerMenu = rescue(
        fn () => \App\Models\Menu::query()
            ->where('is_active', true)
            ->where('location', 'header')
            ->with([
                'items' => fn ($q) => $q->where('is_active', true),
                'items.linkable',
            ])
            ->first(),
        null,
        report: false,
    );

    $headerMenuItems = $headerMenu?->items ?? collect();
@endphp

@once
    {{-- أنماط البحث الفوري (غلاف + عنوان + سعر). مضمّنة (بلا بناء أصول): <style> داخل
         الصفحة يعمل في كل الصفحات لأن الهيدر مُضمَّن دائمًا. --}}
    <style>
        .s-res{ display:flex; align-items:center; gap:12px; padding:9px 12px; text-decoration:none; color:var(--ink); border-radius:12px; }
        .s-res:hover,.s-res.is-active{ background:var(--purple-soft); }
        .s-res__thumb{ flex:0 0 auto; width:42px; height:54px; border-radius:8px; overflow:hidden; background:var(--purple-soft); display:grid; place-items:center; border:1px solid var(--line); }
        .s-res__thumb img{ width:100%; height:100%; object-fit:cover; }
        .s-res__ph{ font-weight:900; font-size:18px; color:var(--purple); }
        .s-res__body{ flex:1 1 auto; min-width:0; display:flex; flex-direction:column; gap:2px; }
        .s-res__title{ font-weight:800; font-size:14px; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .s-res__sub{ font-size:11.5px; color:var(--ink-soft); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .s-res__price{ flex:0 0 auto; font-weight:900; font-size:13.5px; color:var(--purple); white-space:nowrap; }
        .s-suggest{ padding:6px; }
        .s-empty{ display:flex; align-items:center; gap:10px; padding:16px 14px; color:var(--ink-soft); font-size:13.5px; }
        /* شاشة بحث الموبايل */
        .s-ov-top{ display:flex; align-items:center; gap:10px; }
        .s-ov-form{ flex:1 1 auto; display:flex; align-items:center; gap:10px; background:var(--surface); border:2px solid var(--line); border-radius:var(--r-pill); padding:10px 16px; transition:border-color .2s; }
        .s-ov-form:focus-within{ border-color:var(--purple); }
        .s-ov-icon{ color:var(--ink-faint); display:grid; place-items:center; flex:0 0 auto; }
        .s-ov-form input{ flex:1; min-width:0; border:0; background:transparent; font-family:inherit; font-size:15px; color:var(--ink); outline:none; }
        .s-results{ margin-top:14px; display:flex; flex-direction:column; gap:8px; max-height:72vh; overflow-y:auto; -webkit-overflow-scrolling:touch; }
        .s-results .s-res{ padding:10px 12px; border:1px solid var(--line); background:var(--surface); box-shadow:var(--shadow-s); }
        .s-hint{ margin-top:26px; text-align:center; color:var(--ink-soft); font-size:14px; }
    </style>

    {{-- تسجيل مخزن بحث مشترك (Alpine store) يخدم شريط سطح المكتب وشاشة الموبايل معًا:
         يُحمّل فهرس الكتب مرّة واحدة ويفلتره في المتصفح لحظيًا. سكربت كلاسيكي يسبق وحدة
         app.js المؤجّلة فيُسجّل مستمع alpine:init قبل Alpine.start(). --}}
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('search', {
                    q: '',
                    all: null,
                    items: [],
                    open: false,
                    active: -1,
                    loaded: false,
                    loading: false,
                    indexUrl: @js(route('search.index')),
                    norm(s) {
                        return (s || '').toString().toLowerCase()
                            .replace(/[ً-ٰٟـ]/g, '')
                            .replace(/[أإآ]/g, 'ا')
                            .replace(/ى/g, 'ي')
                            .replace(/ة/g, 'ه')
                            .replace(/ؤ/g, 'و')
                            .replace(/ئ/g, 'ي')
                            .replace(/ء/g, '')
                            .replace(/\s+/g, ' ')
                            .trim();
                    },
                    async load() {
                        // حارس ضد النداء المزدوج (شريط سطح المكتب + شاشة الموبايل معًا)
                        // كي لا يُطلب الفهرس مرّتين على كل صفحة.
                        if (this.all || this.loading || !this.indexUrl) return;
                        this.loading = true;
                        try {
                            const res = await fetch(this.indexUrl, { headers: { Accept: 'application/json' } });
                            if (res.ok) {
                                const data = await res.json();
                                this.all = (data.books || []).map((b) => ({
                                    t: b.t, a: b.a, p: b.p, u: b.u, img: b.img, pr: b.pr,
                                    title: this.norm(b.t),
                                    hay: this.norm(`${b.t} ${b.a || ''} ${b.p || ''}`),
                                }));
                                this.loaded = true;
                                if (this.q.trim()) this.filter();
                            }
                        } catch (e) {} finally {
                            this.loading = false;
                        }
                    },
                    filter() {
                        const term = this.norm(this.q).replace(/^ال/, '');
                        this.active = -1;
                        if (!term) { this.items = []; this.open = false; return; }
                        this.open = true; // يوجد نص → أظهر القائمة (نتائج أو رسالة «لا نتائج»)
                        if (!this.all) { this.load(); return; }
                        const titleHits = [], otherHits = [];
                        for (const it of this.all) {
                            if (it.title.includes(term)) titleHits.push(it);
                            else if (it.hay.includes(term)) otherHits.push(it);
                        }
                        this.items = titleHits.concat(otherHits).slice(0, 8);
                    },
                    move(dir) {
                        if (!this.items.length) return;
                        this.active = (this.active + dir + this.items.length) % this.items.length;
                    },
                    onEnter(e) {
                        if (this.open && this.active >= 0 && this.items[this.active]) {
                            e.preventDefault();
                            window.location.href = this.items[this.active].u;
                        }
                    },
                    reopen() { if (this.q.trim()) this.open = true; },
                    close() { this.open = false; this.active = -1; },
                });
            });
        </script>
    @endpush
@endonce

<header>
    <div class="nav">
        <div class="wrap">
            <a class="logo" href="{{ route('home') }}" aria-label="{{ __('common.brand') }}">
                <img class="logo-img" src="{{ asset('images/logo.webp') }}" alt="{{ __('common.brand') }}" width="440" height="318">
            </a>

            {{-- بحث سطح المكتب مع اقتراح فوري خفيف (Alpine store مشترك) — يُخفى على الموبايل.
                 x-init يضبط النص الحالي من الرابط ويُحمّل الفهرس مرّة واحدة (يعمل حتى وهو مخفي). --}}
            <div class="searchbar-wrap"
                x-init="$store.search.q = @js((string) request('q')); $store.search.load()"
                @click.outside="$store.search.close()">
                <form class="searchbar" action="{{ route('search') }}" method="get" role="search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                        stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m21 21-4.3-4.3"></path>
                    </svg>
                    <input type="search" name="q" x-model="$store.search.q" autocomplete="off"
                        role="combobox" aria-controls="search-suggest" aria-autocomplete="list"
                        :aria-expanded="$store.search.open.toString()"
                        @input="$store.search.filter()"
                        @focus="$store.search.reopen()"
                        @keydown.arrow-down.prevent="$store.search.move(1)"
                        @keydown.arrow-up.prevent="$store.search.move(-1)"
                        @keydown.enter="$store.search.onEnter($event)"
                        @keydown.escape="$store.search.close()"
                        placeholder="{{ __('common.search_placeholder') }}"
                        aria-label="{{ __('common.search_placeholder') }}">
                </form>

                <div class="suggest s-suggest" id="search-suggest" role="listbox"
                    x-show="$store.search.open && ($store.search.loaded || $store.search.items.length > 0)" x-cloak
                    x-transition.opacity aria-label="{{ __('search.suggest_label') }}">
                    @include('partials.search-results')
                </div>
            </div>

            <div class="nav-actions">
                {{-- زر البحث (موبايل) --}}
                <button type="button" class="icon-btn only-mobile" @click="searchOpen = true"
                    aria-label="{{ __('common.open_search') }}"><x-ui-icon name="search" /></button>

                {{-- تبديل الوضع الليلي/النهاري --}}
                <button type="button" class="icon-btn" @click="$store.theme.toggle()"
                    aria-label="{{ __('common.toggle_theme') }}">
                    <span x-show="!$store.theme.isDark" aria-hidden="true"><x-ui-icon name="moon" /></span>
                    <span x-show="$store.theme.isDark" aria-hidden="true" x-cloak><x-ui-icon name="sun" /></span>
                </button>

                {{-- السلة --}}
                <button type="button" class="icon-btn" @click="$store.cart.open = true"
                    aria-label="{{ __('nav.cart') }}">
                    <x-ui-icon name="cart" />
                    <span class="cart-badge" x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak></span>
                </button>

                {{-- قائمة الموبايل --}}
                <button type="button" class="icon-btn only-mobile" @click="menuOpen = true"
                    aria-label="{{ __('common.open_menu') }}"><x-ui-icon name="menu" /></button>
            </div>
        </div>
    </div>

    {{-- شريط الأقسام --}}
    <nav class="catstrip" aria-label="{{ __('nav.categories') }}">
        <div class="wrap">
            <a class="catlink" href="{{ route('books.index') }}"
                @if (request()->routeIs('books.index')) aria-current="page" @endif>
                <span class="e" aria-hidden="true">🧸</span> {{ __('nav.all_books') }}
            </a>
            @foreach ($navCategories as $cat)
                <a class="catlink" href="{{ route('categories.show', $cat) }}"
                    @if (request()->routeIs('categories.show') && request()->route('category')?->id === $cat->id) aria-current="page" @endif>
                    @if (filled($cat->icon))<span class="e" aria-hidden="true">{{ $cat->icon }}</span>@endif
                    {{ $cat->name }}
                </a>
            @endforeach
            <a class="catlink" href="{{ route('books.offers') }}">
                <span class="e" aria-hidden="true">🎁</span> {{ __('nav.offers') }}
            </a>

            <a class="catlink" href="{{ route('blog.index') }}"
                @if (request()->routeIs('blog.*')) aria-current="page" @endif>
                <span class="e" aria-hidden="true">📖</span> {{ __('nav.blog') }}
            </a>

            {{-- روابط إضافية من قائمة الهيدر (CMS) — تُلحق بشريط الأقسام دون كسر الافتراضي --}}
            @foreach ($headerMenuItems as $mi)
                @php $mu = $resolveMenuUrl($mi); @endphp
                @if ($mu)
                    <a class="catlink" href="{{ $mu }}"@if ($mi->target === '_blank') target="_blank" rel="noopener"@endif>
                        @if (filled($mi->icon))<span class="e" aria-hidden="true">{{ $mi->icon }}</span>@endif
                        {{ $mi->label }}
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
</header>

{{-- قائمة الموبايل (Drawer) --}}
<template x-teleport="body">
    <div x-show="menuOpen" x-cloak>
        <div class="drawer-backdrop" @click="menuOpen = false" x-transition.opacity></div>
        <div class="drawer" x-transition role="dialog" aria-modal="true" aria-label="{{ __('nav.categories') }}">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
                <span style="font-weight:900">{{ __('common.brand') }}</span>
                <button type="button" class="icon-btn" @click="menuOpen = false"
                    aria-label="{{ __('common.close') }}"><x-ui-icon name="close" /></button>
            </div>
            <a href="{{ route('home') }}">🏠 {{ __('nav.home') }}</a>
            <a href="{{ route('books.index') }}">🧸 {{ __('nav.all_books') }}</a>
            <a href="{{ route('books.offers') }}">🎁 {{ __('nav.offers') }}</a>
            <a href="{{ route('blog.index') }}">📖 {{ __('nav.blog') }}</a>
            @foreach ($navCategories as $cat)
                <a href="{{ route('categories.show', $cat) }}">
                    @if (filled($cat->icon)){{ $cat->icon }}@else 📚 @endif {{ $cat->name }}
                </a>
            @endforeach

            {{-- روابط قائمة الهيدر (CMS) داخل درج الموبايل --}}
            @foreach ($headerMenuItems as $mi)
                @php $mu = $resolveMenuUrl($mi); @endphp
                @if ($mu)
                    <a href="{{ $mu }}"@if ($mi->target === '_blank') target="_blank" rel="noopener"@endif>
                        @if (filled($mi->icon)){{ $mi->icon }} @else 🔗 @endif{{ $mi->label }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</template>

{{-- شاشة البحث (موبايل) — بحث فوري: يظهر اسم الكتاب وصورته وسعره تحت خانة البحث --}}
<template x-teleport="body">
    <div class="search-overlay" x-show="searchOpen" x-cloak x-transition role="dialog" aria-modal="true"
        aria-label="{{ __('common.search_submit') }}">
        <div class="s-ov-top">
            <form class="s-ov-form" action="{{ route('search') }}" method="get" role="search">
                <span class="s-ov-icon" aria-hidden="true"><x-ui-icon name="search" :size="20" /></span>
                <input type="search" name="q" x-model="$store.search.q" autocomplete="off"
                    @input="$store.search.filter()"
                    @keydown.escape="searchOpen = false"
                    placeholder="{{ __('common.search_placeholder') }}"
                    aria-label="{{ __('common.search_placeholder') }}"
                    aria-controls="s-ov-results" role="combobox" aria-autocomplete="list"
                    x-init="$store.search.load(); $watch('searchOpen', v => { if (v) { $store.search.load(); $nextTick(() => $el.focus()); } })">
            </form>
            <button type="button" class="icon-btn" @click="searchOpen = false"
                aria-label="{{ __('common.close') }}"><x-ui-icon name="close" /></button>
        </div>

        {{-- النتائج الفورية أسفل خانة البحث (غلاف + عنوان + سعر) --}}
        <div class="s-results" id="s-ov-results" role="listbox" aria-label="{{ __('search.suggest_label') }}"
            x-show="$store.search.q.trim().length > 0">
            @include('partials.search-results')
        </div>

        {{-- تلميح قبل الكتابة --}}
        <p class="s-hint" x-show="! $store.search.q.trim().length" x-cloak>{{ __('search.live_hint') }}</p>
    </div>
</template>
