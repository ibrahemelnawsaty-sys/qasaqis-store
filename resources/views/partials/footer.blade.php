@php
    // روابط السوشيال: مفاتيح social_* مقروءة من المصفوفة المشتركة $storeSettings المبنيّة
    // مرّةً في AppServiceProvider من نفس جدول الإعدادات (تحوي كل مفاتيح social_* ضمن
    // defaults). كان هنا استعلام Setting منفصل يكرّر القراءة نفسها على كل صفحة — أُزيل.
    // توافق خلفي: عند غياب مفتاح social_* نرجع لقيم *_url القديمة إن مُرِّرت في $storeSettings.
    $socialLink = static function (string $key, string $legacy = '') use ($storeSettings): string {
        $value = (string) ($storeSettings[$key] ?? '');

        if (blank($value) && $legacy !== '') {
            $value = (string) ($storeSettings[$legacy] ?? '');
        }

        return $value;
    };

    // كل رابط سوشيال: [الرابط، أيقونة emoji خفيفة، مفتاح aria]. يُعرض فقط إن كان غير فارغ.
    $socials = [];
    foreach ([
        ['social_facebook', 'facebook_url', 'facebook', 'social_facebook'],
        ['social_instagram', 'instagram_url', 'instagram', 'social_instagram'],
        ['social_tiktok', 'tiktok_url', 'tiktok', 'social_tiktok'],
        ['social_youtube', '', 'youtube', 'social_youtube'],
        ['social_twitter', '', 'twitter', 'social_twitter'],
        ['social_snapchat', '', 'snapchat', 'social_snapchat'],
        ['social_telegram', '', 'telegram', 'social_telegram'],
    ] as [$key, $legacy, $icon, $ariaKey]) {
        $href = $socialLink($key, $legacy);

        if (filled($href)) {
            $socials[] = ['href' => $href, 'icon' => $icon, 'aria' => $ariaKey];
        }
    }

    // رقم واتساب لبلاطة أيقونة واتساب في صفّ السوشيال.
    $waNumber = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));

    // قائمة الفوتر (CMS، Menu location=footer) مخزّنة مؤقّتًا كبنية مُحلّلة جاهزة
    // (StorefrontCache): كان استعلام Menu مع eager load للأبناء والربط متعدّد الأشكال
    // يُنفَّذ على كل صفحة. نخزّن مصفوفات بسيطة (روابط محلولة + الأبناء) لا نماذج
    // Eloquent — أخفّ وأأمن للتسلسل. نُبقي العناصر ذات الرابط الفارغ (قد تحمل أبناءً).
    // يُبطَل عند حفظ أي Menu/MenuItem. rescue تُبقي الفوتر يعمل قبل الهجرات.
    $footerMenuItems = rescue(
        fn (): array => \Illuminate\Support\Facades\Cache::remember(
            \App\Support\Cache\StorefrontCache::menuKey('footer'),
            \App\Support\Cache\StorefrontCache::TTL,
            static function (): array {
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

                $menu = \App\Models\Menu::query()
                    ->where('is_active', true)
                    ->where('location', 'footer')
                    ->with([
                        'items' => fn ($q) => $q->where('is_active', true),
                        'items.children' => fn ($q) => $q->where('is_active', true),
                        'items.linkable',
                        'items.children.linkable',
                    ])
                    ->first();

                return ($menu?->items ?? collect())
                    ->map(fn ($mi): array => [
                        'url' => $resolveMenuUrl($mi),
                        'label' => $mi->label,
                        'target' => $mi->target,
                        'children' => $mi->children
                            ->map(fn ($child): array => [
                                'url' => $resolveMenuUrl($child),
                                'label' => $child->label,
                                'target' => $child->target,
                            ])
                            ->all(),
                    ])
                    ->all();
            }
        ),
        [],
        report: false,
    );
@endphp

<footer class="ft">
    <div class="wrap">
        <div class="ft-grid">
            <div>
                <a class="logo" href="{{ route('home') }}" aria-label="{{ __('common.brand') }}">
                    <img class="logo-img logo-img--footer" src="{{ asset('images/logo.webp') }}" alt="{{ __('common.brand') }}" width="440" height="318">
                </a>
                <p class="ft-about">{{ __('footer.about') }}</p>
                <div class="socials">
                    @foreach ($socials as $social)
                        <a href="{{ $social['href'] }}" target="_blank" rel="noopener" aria-label="{{ __('footer.' . $social['aria']) }}">
                            <x-social-icon :name="$social['icon']" />
                        </a>
                    @endforeach
                    @if (filled($waNumber))
                        <a href="https://wa.me/{{ $waNumber }}" target="_blank" rel="noopener" aria-label="{{ __('footer.social_whatsapp') }}">
                            <x-social-icon name="whatsapp" />
                        </a>
                    @endif
                </div>
            </div>

            <div>
                <h5>{{ __('footer.shop_heading') }}</h5>
                <div class="ft-links">
                    <a href="{{ route('books.index') }}">{{ __('footer.link_all_books') }}</a>
                    <a href="{{ route('books.offers') }}">{{ __('footer.link_offers') }}</a>
                    <a href="{{ route('blog.index') }}">{{ __('nav.blog') }}</a>
                    <a href="{{ route('search') }}">{{ __('common.search_submit') }}</a>
                    <a href="{{ route('orders.track.show') }}">{{ __('footer.link_track') }}</a>

                    {{-- روابط قائمة الفوتر (CMS: من نحن، الشحن، الاسترجاع…) تُلحق بعد الافتراضي --}}
                    @foreach ($footerMenuItems as $mi)
                        @if ($mi['url'])
                            <a href="{{ $mi['url'] }}"@if ($mi['target'] === '_blank') target="_blank" rel="noopener"@endif>{{ $mi['label'] }}</a>
                        @endif
                        @foreach ($mi['children'] as $child)
                            @if ($child['url'])
                                <a href="{{ $child['url'] }}"@if ($child['target'] === '_blank') target="_blank" rel="noopener"@endif>{{ $child['label'] }}</a>
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
                    @if (filled($storeSettings['store_maps_url'] ?? ''))
                        <a href="{{ $storeSettings['store_maps_url'] }}" target="_blank" rel="noopener">📍 {{ __('footer.visit_us') }}</a>
                    @endif
                    @if (filled($storeSettings['contact_address'] ?? ''))
                        <span style="color:var(--ink-soft);font-size:13px;line-height:1.6">{{ $storeSettings['contact_address'] }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="ft-bottom">
            <span>{{ __('footer.rights', ['year' => date('Y')]) }}</span>
            <span class="dom">{{ __('common.domain') }}</span>
        </div>
    </div>
</footer>
