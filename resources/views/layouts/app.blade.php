<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl"
    data-wa-order-intro="{{ __('common.wa_order_intro') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- منع وميض الثيم (FOUC): يضبط data-theme قبل رسم الصفحة من تخزين المتصفح. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('qasaqis-theme');
                if (t === 'dark' || t === 'light') {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) {}
        })();
    </script>

    <title>@yield('title', __('common.brand') . ' — ' . __('common.tagline'))</title>
    <meta name="description" content="@yield('meta_description', __('common.tagline'))">

    {{-- خطوط عربية مستضافة محليًا (@font-face في app.css) — بلا حجب عرض ولا اعتماد خارجي.
         preload للخطّين الحرجين فقط (نص Tajawal + عناوين Baloo) لتقليل قفز التخطيط. --}}
    <link rel="preload" href="{{ asset('fonts/tajawal-400-ar.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/baloo-800-ar.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- أيقونة المتصفح --}}
    <link rel="icon" type="image/webp" href="{{ asset('images/logo.webp') }}">

    {{-- ===== SEO تقني افتراضي — تُغلَب قيمه عند دفع الصفحة عبر الأقسام/الـ stacks ===== --}}
    {{-- robots افتراضي: فهرسة وتتبّع؛ تغلبه الصفحة بـ @section('seo_robots', 'noindex, nofollow'). --}}
    <meta name="robots" content="@yield('seo_robots', 'index, follow')">

    {{-- الرابط الأساسي (canonical): الرابط الحالي افتراضيًا؛ تغلبه الصفحة بـ @section('seo_canonical', ...). --}}
    <link rel="canonical" href="@yield('seo_canonical', url()->current())">

    {{-- hreflang: الموقع عربي لكل الدول العربية → بديل ar و x-default لنفس الرابط. --}}
    <link rel="alternate" hreflang="ar" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}">

    {{-- لون واجهة المتصفح (بنفسجي العلامة — بند 0.1). --}}
    <meta name="theme-color" content="{{ config('seo.theme_color', '#5B2A86') }}">

    {{-- Open Graph افتراضي: العنوان/الوصف من قسمَي الصفحة (title/meta_description) تلقائيًا. --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ __('common.brand') }}">
    <meta property="og:locale" content="{{ config('seo.og_locale', 'ar_AR') }}">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="@yield('title', __('common.brand') . ' — ' . __('common.tagline'))">
    <meta property="og:description" content="@yield('meta_description', __('common.tagline'))">
    <meta property="og:image" content="@yield('og_image', asset(config('seo.default_image', 'images/logo.png')))">

    {{-- Twitter Card افتراضي. --}}
    <meta name="twitter:card" content="{{ config('seo.twitter_card', 'summary_large_image') }}">
    <meta name="twitter:title" content="@yield('title', __('common.brand') . ' — ' . __('common.tagline'))">
    <meta name="twitter:description" content="@yield('meta_description', __('common.tagline'))">
    <meta name="twitter:image" content="@yield('og_image', asset(config('seo.default_image', 'images/logo.png')))">

    {{-- JSON-LD ثابت للموقع: Organization + WebSite (بحث داخلي). أعلام HEX تمنع كسر </script>. --}}
    @php
        $seoSiteUrl = rtrim((string) config('seo.site_url'), '/');
        $seoName = __('common.brand');
        $seoLogo = asset(config('seo.default_image', 'images/logo.png'));
        // sameAs من روابط السوشيال غير الفارغة المشتركة في $storeSettings (بند 1.1: مفاتيح فعلية موجودة).
        $seoSameAs = array_values(array_filter([
            $storeSettings['social_facebook'] ?? '',
            $storeSettings['social_instagram'] ?? '',
            $storeSettings['social_tiktok'] ?? '',
            $storeSettings['social_youtube'] ?? '',
            $storeSettings['social_twitter'] ?? '',
            $storeSettings['social_snapchat'] ?? '',
            $storeSettings['social_telegram'] ?? '',
        ], static fn ($u): bool => filled($u)));

        $seoOrg = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $seoName,
            'url' => $seoSiteUrl,
            'logo' => $seoLogo,
        ];
        if ($seoSameAs !== []) {
            $seoOrg['sameAs'] = $seoSameAs;
        }

        $seoWebSite = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $seoName,
            'url' => $seoSiteUrl,
            'inLanguage' => 'ar',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $seoSiteUrl . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $seoJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    @endphp
    <script type="application/ld+json">{!! json_encode($seoOrg, $seoJsonFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($seoWebSite, $seoJsonFlags) !!}</script>

    {{-- وسوم SEO لكل صفحة (canonical / Open Graph / JSON-LD) تدفعها الصفحات (بند 0.8). --}}
    @stack('meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- حاجز وقائي: body فيه overflow-x:hidden لكن html لا؛ هذا يمنع أي عنصر
         (مثل مصيدة السبام سابقًا) من إحداث تمرير أفقي على الجوال في RTL. --}}
    <style>html{overflow-x:hidden}</style>
    @include('partials.analytics-head')
    @stack('head')
</head>
{{-- body_class: نقش خلفية القسم (.pat-* في app.css). القوالب بلا @section تبقى بلا نقش. --}}
<body x-data="shell" class="@yield('body_class')">
    @include('partials.analytics-body')
    <a href="#main" class="skip-link btn btn-primary">{{ __('common.skip_to_content') }}</a>

    @include('partials.header')

    <main id="main">
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- زر واتساب العائم --}}
    <x-wa-button :class="'wa-float'" :aria="__('common.order_whatsapp')" :icon="true" />

    @include('partials.cart-drawer')

    {{-- بوب أب CMS النشط (دعاية/استبيان/نشرة/إعلان) — لا يُعرض إن لم يوجد نشط --}}
    @include('partials.popup')

    @include('partials.consent-banner')

    @stack('scripts')
</body>
</html>
