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

    @stack('scripts')
</body>
</html>
