@php($ax = $analytics ?? [])
@if (($ax['enabled'] ?? false) && (filled($ax['ga4_id'] ?? null) || filled($ax['meta_pixel_id'] ?? null)))
    {{-- تحليلات M6: Consent Mode v2 (مرفوض افتراضيًا) + تحميل مؤجّل بعد الخمول. --}}
    <script>
        (function () {
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            window.gtag = gtag;
            gtag('consent', 'default', {
                ad_storage: 'denied', analytics_storage: 'denied',
                ad_user_data: 'denied', ad_personalization: 'denied', wait_for_update: 500
            });
            window.__qs = { ga4: @js($ax['ga4_id'] ?? null), pixel: @js($ax['meta_pixel_id'] ?? null), loaded: false };
            window.__qsLoadAnalytics = function () {
                var q = window.__qs;
                if (q.loaded) return; q.loaded = true;
                if (q.ga4) {
                    var s = document.createElement('script'); s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + q.ga4;
                    document.head.appendChild(s);
                    gtag('js', new Date()); gtag('config', q.ga4);
                }
                if (q.pixel) {
                    !function (f, b, e, v, n, t, s) { if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }; if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0; t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s) }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
                    fbq('consent', 'revoke'); fbq('init', q.pixel); fbq('track', 'PageView');
                }
            };
            if ('requestIdleCallback' in window) { requestIdleCallback(window.__qsLoadAnalytics, { timeout: 4000 }); }
            else { window.addEventListener('load', function () { setTimeout(window.__qsLoadAnalytics, 1200); }); }
        })();
    </script>
@endif
