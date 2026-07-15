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

    {{-- خطوط عربية احترافية: Baloo Bhaijaan 2 (عناوين مرحة للأطفال) + Tajawal (نص نظيف للأمهات). --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
        href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@500;600;700;800&family=Tajawal:wght@400;500;700&display=swap">
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@500;600;700;800&family=Tajawal:wght@400;500;700&display=swap">

    {{-- أيقونة المتصفح --}}
    <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">

    {{-- وسوم SEO لكل صفحة (canonical / Open Graph / JSON-LD) تدفعها الصفحات (بند 0.8). --}}
    @stack('meta')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body x-data="shell">
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

    @stack('scripts')
</body>
</html>
