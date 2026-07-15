@php
    // روابط السوشيال: المصدر الرسمي هو مفاتيح social_* في جدول الإعدادات (CMS، يحرّرها
    // الأدمن من «إعدادات المتجر»). نقرأها مباشرةً باستعلام واحد خفيف على عمود key الفريد،
    // لأن المصفوفة المشتركة $storeSettings (المبنية في AppServiceProvider، خارج نطاق هذا
    // الملف) لا تمرّر إلا مجموعة جزئية قديمة. rescue: تُعرض الصفحة حتى قبل الهجرة/البذر.
    $socialRows = rescue(
        fn () => \App\Models\Setting::query()
            ->whereIn('key', [
                'social_facebook', 'social_instagram', 'social_tiktok',
                'social_youtube', 'social_twitter', 'social_snapchat', 'social_telegram',
            ])
            ->pluck('value', 'key')
            ->toArray(),
        [],
        report: false,
    );

    // توافق خلفي: عند غياب مفتاح social_* نرجع لقيم *_url القديمة المشتركة عبر $storeSettings.
    $socialLink = static function (string $key, string $legacy = '') use ($socialRows, $storeSettings): string {
        $value = (string) ($socialRows[$key] ?? '');

        if (blank($value) && $legacy !== '') {
            $value = (string) ($storeSettings[$legacy] ?? '');
        }

        return $value;
    };

    // كل رابط سوشيال: [الرابط، أيقونة emoji خفيفة، مفتاح aria]. يُعرض فقط إن كان غير فارغ.
    $socials = [];
    foreach ([
        ['social_facebook', 'facebook_url', '📘', 'social_facebook'],
        ['social_instagram', 'instagram_url', '📸', 'social_instagram'],
        ['social_tiktok', 'tiktok_url', '🎵', 'social_tiktok'],
        ['social_youtube', '', '▶️', 'social_youtube'],
        ['social_twitter', '', '🐦', 'social_twitter'],
        ['social_snapchat', '', '👻', 'social_snapchat'],
        ['social_telegram', '', '✈️', 'social_telegram'],
    ] as [$key, $legacy, $icon, $ariaKey]) {
        $href = $socialLink($key, $legacy);

        if (filled($href)) {
            $socials[] = ['href' => $href, 'icon' => $icon, 'aria' => $ariaKey];
        }
    }

    // قائمة الفوتر المُدارة من الـ CMS (Menu location=footer). eager load للعناصر
    // وأبنائها وربطها لتفادي N+1. عند غيابها تبقى الروابط الافتراضية كما هي.
    $resolveMenuUrl = static function ($item): ?string {
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

    $footerMenu = rescue(
        fn () => \App\Models\Menu::query()
            ->where('is_active', true)
            ->where('location', 'footer')
            ->with([
                'items' => fn ($q) => $q->where('is_active', true),
                'items.children' => fn ($q) => $q->where('is_active', true),
                'items.linkable',
                'items.children.linkable',
            ])
            ->first(),
        null,
        report: false,
    );

    $footerMenuItems = $footerMenu?->items ?? collect();
@endphp

<footer class="ft">
    <div class="wrap">
        <div class="ft-grid">
            <div>
                <a class="logo" href="{{ route('home') }}" aria-label="{{ __('common.brand') }}">
                    <img class="logo-img logo-img--footer" src="{{ asset('images/logo.png') }}" alt="{{ __('common.brand') }}" width="440" height="318">
                </a>
                <p class="ft-about">{{ __('footer.about') }}</p>
                <div class="socials">
                    @foreach ($socials as $social)
                        <a href="{{ $social['href'] }}" target="_blank" rel="noopener" aria-label="{{ __('footer.' . $social['aria']) }}">{{ $social['icon'] }}</a>
                    @endforeach
                    <x-wa-button :class="'socials-wa'" :aria="__('footer.social_whatsapp')" :label="'💬'" />
                </div>
            </div>

            <div>
                <h5>{{ __('footer.shop_heading') }}</h5>
                <div class="ft-links">
                    <a href="{{ route('books.index') }}">{{ __('footer.link_all_books') }}</a>
                    <a href="{{ route('books.offers') }}">{{ __('footer.link_offers') }}</a>
                    <a href="{{ route('search') }}">{{ __('common.search_submit') }}</a>

                    {{-- روابط قائمة الفوتر (CMS: من نحن، الشحن، الاسترجاع…) تُلحق بعد الافتراضي --}}
                    @foreach ($footerMenuItems as $mi)
                        @php $mu = $resolveMenuUrl($mi); @endphp
                        @if ($mu)
                            <a href="{{ $mu }}"@if ($mi->target === '_blank') target="_blank" rel="noopener"@endif>{{ $mi->label }}</a>
                        @endif
                        @foreach ($mi->children as $child)
                            @php $cu = $resolveMenuUrl($child); @endphp
                            @if ($cu)
                                <a href="{{ $cu }}"@if ($child->target === '_blank') target="_blank" rel="noopener"@endif>{{ $child->label }}</a>
                            @endif
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div>
                <h5>{{ __('nav.categories') }}</h5>
                <div class="ft-links">
                    @foreach ($navCategories as $cat)
                        <a href="{{ route('categories.show', $cat) }}">{{ $cat->name }}</a>
                    @endforeach
                </div>
            </div>

            <div>
                <h5>{{ __('footer.contact_heading') }}</h5>
                <div class="ft-links">
                    <x-wa-button :class="'ft-wa-link'" :label="__('footer.link_whatsapp')" />
                </div>
            </div>
        </div>

        <div class="ft-bottom">
            <span>{{ __('footer.rights', ['year' => date('Y')]) }}</span>
            <span class="dom">{{ __('common.domain') }}</span>
        </div>
    </div>
</footer>
