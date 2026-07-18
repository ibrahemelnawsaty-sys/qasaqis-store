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

<header>
    <div class="nav">
        <div class="wrap">
            <a class="logo" href="{{ route('home') }}" aria-label="{{ __('common.brand') }}">
                <img class="logo-img" src="{{ asset('images/logo.webp') }}" alt="{{ __('common.brand') }}" width="440" height="318">
            </a>

            {{-- بحث سطح المكتب مع اقتراح فوري خفيف (Alpine) — يُخفى على الموبايل --}}
            <div class="searchbar-wrap" x-data="searchBox(@js((string) request('q')))"
                data-index-url="{{ route('search.index') }}" @click.outside="close()">
                <form class="searchbar" action="{{ route('search') }}" method="get" role="search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                        stroke-linecap="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m21 21-4.3-4.3"></path>
                    </svg>
                    <input type="search" name="q" x-model="q" autocomplete="off"
                        role="combobox" aria-controls="search-suggest" aria-autocomplete="list"
                        :aria-expanded="open.toString()"
                        @input="filter()"
                        @focus="reopen()"
                        @keydown.arrow-down.prevent="move(1)"
                        @keydown.arrow-up.prevent="move(-1)"
                        @keydown.enter="onEnter($event)"
                        @keydown.escape="close()"
                        placeholder="{{ __('common.search_placeholder') }}"
                        aria-label="{{ __('common.search_placeholder') }}">
                </form>

                <div class="suggest" id="search-suggest" role="listbox" x-show="open" x-cloak
                    x-transition.opacity aria-label="{{ __('search.suggest_label') }}">
                    <template x-for="(item, i) in items" :key="item.kind + '-' + i">
                        <a :href="item.url" role="option" class="suggest-item"
                            :class="{ active: i === active }" :aria-selected="(i === active).toString()"
                            @mouseenter="active = i">
                            <span class="si" aria-hidden="true" x-text="icon(item.kind)"></span>
                            <span x-text="item.label"></span>
                        </a>
                    </template>
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

{{-- شاشة البحث (موبايل) --}}
<template x-teleport="body">
    <div class="search-overlay" x-show="searchOpen" x-cloak x-transition role="dialog" aria-modal="true"
        aria-label="{{ __('common.search_submit') }}">
        <div style="display:flex;justify-content:flex-end">
            <button type="button" class="icon-btn" @click="searchOpen = false"
                aria-label="{{ __('common.close') }}">✕</button>
        </div>
        <form action="{{ route('search') }}" method="get" role="search">
            <input type="search" name="q" value="{{ request('q') }}"
                placeholder="{{ __('common.search_placeholder') }}"
                aria-label="{{ __('common.search_placeholder') }}" autofocus
                x-init="$watch('searchOpen', v => v && $nextTick(() => $el.focus()))">
            <button type="submit" class="btn btn-primary">{{ __('common.search_submit') }}</button>
        </form>
    </div>
</template>
