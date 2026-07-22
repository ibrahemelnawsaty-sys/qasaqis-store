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

    {{-- تحقّق Google Search Console (طريقة meta-tag) — يظهر فقط عند ضبط GOOGLE_SITE_VERIFICATION. --}}
    @if (filled(config('seo.google_site_verification')))
        <meta name="google-site-verification" content="{{ config('seo.google_site_verification') }}">
    @endif

    {{-- خطوط عربية مستضافة محليًا (@font-face في app.css) — بلا حجب عرض ولا اعتماد خارجي.
         preload للخطّين الحرجين فقط (نص Tajawal + عناوين Baloo) لتقليل قفز التخطيط. --}}
    <link rel="preload" href="{{ asset('fonts/tajawal-400-ar.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/baloo-800-ar.woff2') }}" as="font" type="font/woff2" crossorigin>

    {{-- أيقونة المتصفح ونتائج البحث.
         Google يشترط أيقونة مربّعة (1:1) بصيغة ICO/PNG/SVG/JPEG/GIF/BMP ولا يدعم WebP،
         ويوصي بأكبر من 48×48. لذلك نخدم ICO متعدد الأحجام + PNG مربّعًا 512.
         الروابط ثابتة عمدًا: تغييرها يُبطل ما خزّنه Google ويؤخّر ظهور الأيقونة. --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/icon-192.png') }}">

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

    {{-- og_title/og_description: منفذان اختياريان تغلب بهما الصفحة عنوان/وصف المشاركة
         دون تغيير <title> ووصف الميتا. الصفحات التي لا تعرّفهما ترث القسمين العاديين.
         (صفحات CMS كانت تدفع og:title ثانيًا عبر stack فيأتي بعد وسم التخطيط،
         ومعظم المحلّلات تأخذ الأول — أي أن og_title الذي يضبطه الأدمن كان مُهمَلًا.)

         تُحسب هنا في PHP لا بـ @hasSection داخل السمة: Blade يطابق التوجيهات بـ \B@
         فلا يتعرّف على توجيه يلاصق حرف كلمة — و«@else@yield» كان يُبقي الـ yield نصًّا
         خامًا يُطبع حرفيًا في الوسم. كما أن {{ }} تُهرِّب المحتوى، بخلاف @yield. --}}
    @php
        $seoDefaultTitle = __('common.brand') . ' — ' . __('common.tagline');
        $seoTitle = trim($__env->yieldContent('title')) ?: $seoDefaultTitle;
        $seoDescription = trim($__env->yieldContent('meta_description')) ?: __('common.tagline');
        $seoOgTitle = trim($__env->yieldContent('og_title')) ?: $seoTitle;
        $seoOgDescription = trim($__env->yieldContent('og_description')) ?: $seoDescription;
    @endphp

    {{-- Open Graph افتراضي: العنوان/الوصف من قسمَي الصفحة (title/meta_description) تلقائيًا. --}}
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ __('common.brand') }}">
    <meta property="og:locale" content="{{ config('seo.og_locale', 'ar_AR') }}">
    <meta property="og:url" content="@yield('og_url', url()->current())">
    <meta property="og:title" content="{{ $seoOgTitle }}">
    <meta property="og:description" content="{{ $seoOgDescription }}">
    <meta property="og:image" content="@yield('og_image', asset(config('seo.default_image', 'images/logo.png')))">

    {{-- Twitter Card افتراضي. --}}
    <meta name="twitter:card" content="{{ config('seo.twitter_card', 'summary_large_image') }}">
    <meta name="twitter:title" content="{{ $seoOgTitle }}">
    <meta name="twitter:description" content="{{ $seoOgDescription }}">
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
            '@id' => $seoSiteUrl . '/#organization',
            'name' => $seoName,
            'url' => $seoSiteUrl,
            'logo' => $seoLogo,
        ];
        if ($seoSameAs !== []) {
            $seoOrg['sameAs'] = $seoSameAs;
        }

        // alternateName: صيغ لاتينية للعلامة. Google يعتمد WebSite أهمَّ مصدر لاختيار
        // «اسم الموقع» في النتائج، وهذه تساعده على ربط qasaqis اللاتينية بالاسم العربي
        // وتمييزنا عن نطاقات أخرى تتقاسم الرمز نفسه. (بند 6.4: النص من ملف الترجمة.)
        // ملاحظة: __() يُعيد اسم المفتاح نصًّا حين يغيب (مثلًا في لغة بلا ترجمة)، لذا
        // نقبل المصفوفة فقط — وإلا انبعث alternateName وهمي اسمه "common.brand_alt".
        $seoBrandAlt = __('common.brand_alt');
        $seoAltNames = is_array($seoBrandAlt)
            ? array_values(array_filter($seoBrandAlt, 'filled'))
            : [];

        $seoWebSite = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $seoSiteUrl . '/#website',
            'name' => $seoName,
            'url' => $seoSiteUrl,
            'inLanguage' => 'ar',
            'publisher' => ['@id' => $seoSiteUrl . '/#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $seoSiteUrl . '/search?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
        if ($seoAltNames !== []) {
            $seoWebSite['alternateName'] = $seoAltNames;
        }

        // قائمة التنقّل الرئيسية كـSiteNavigationElement — تُخبر Google بأقسام الموقع
        // الأساسية صراحةً (يقوّي فهم البنية، ويُساند ترشيح الروابط الفرعية). روابط
        // مطلقة على دومين الإنتاج مثل باقي الـschema.
        $seoNav = [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            '@id' => $seoSiteUrl . '/#nav',
            'name' => $seoName,
            'itemListElement' => [
                ['@type' => 'SiteNavigationElement', 'position' => 1, 'name' => __('nav.shop'), 'url' => $seoSiteUrl . '/books'],
                ['@type' => 'SiteNavigationElement', 'position' => 2, 'name' => __('nav.offers'), 'url' => $seoSiteUrl . '/offers'],
                ['@type' => 'SiteNavigationElement', 'position' => 3, 'name' => __('nav.blog'), 'url' => $seoSiteUrl . '/blog'],
                ['@type' => 'SiteNavigationElement', 'position' => 4, 'name' => 'من نحن', 'url' => $seoSiteUrl . '/pages/about'],
            ],
        ];

        $seoJsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    @endphp
    <script type="application/ld+json">{!! json_encode($seoOrg, $seoJsonFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($seoWebSite, $seoJsonFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($seoNav, $seoJsonFlags) !!}</script>

    {{-- وسوم SEO لكل صفحة (canonical / Open Graph / JSON-LD) تدفعها الصفحات (بند 0.8). --}}
    @stack('meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- حاجز وقائي: body فيه overflow-x:hidden لكن html لا؛ هذا يمنع أي عنصر
         (مثل مصيدة السبام سابقًا) من إحداث تمرير أفقي على الجوال في RTL. --}}
    <style>html{overflow-x:hidden}</style>
    @include('partials.analytics-head')
    @stack('head')
</head>
{{-- نقش الخلفية: يحلّه BackgroundPatternService من اختيار الأدمن حسب المسار
     (bodyPattern)، ويظل بإمكان أي قالب تجاوزه بـ @section('body_class'). --}}
<body x-data="shell" class="@yield('body_class', $bodyPattern ?? '')">
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

    {{-- رسائل تحقّق المتصفح بالعربية لكل النماذج (M11). --}}
    @include('partials.native-validation')

    @stack('scripts')
</body>
</html>
