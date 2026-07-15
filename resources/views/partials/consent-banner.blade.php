@php($ax = $analytics ?? [])
@if (($ax['enabled'] ?? false) && (filled($ax['ga4_id'] ?? null) || filled($ax['meta_pixel_id'] ?? null)))
    <div x-data="qsConsent()" x-show="show" x-cloak role="dialog" aria-label="{{ __('consent.title') }}" class="qs-consent">
        <p>{{ __('consent.message') }}</p>
        <div class="qs-consent-actions">
            <button type="button" @click="accept()" class="btn btn-primary">{{ __('consent.accept') }}</button>
            <button type="button" @click="reject()" class="btn btn-ghost">{{ __('consent.reject') }}</button>
        </div>
    </div>

    <script>
        function qsConsent() {
            return {
                show: false,
                init() {
                    var c = null;
                    try { c = localStorage.getItem('qs-consent'); } catch (e) {}
                    if (!c) { this.show = true; }
                    else if (c === 'granted') { this.grant(); }
                },
                accept() { try { localStorage.setItem('qs-consent', 'granted'); } catch (e) {} this.grant(); this.show = false; },
                reject() { try { localStorage.setItem('qs-consent', 'denied'); } catch (e) {} this.show = false; },
                grant() {
                    if (window.gtag) { gtag('consent', 'update', { ad_storage: 'granted', analytics_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' }); }
                    if (window.__qsLoadAnalytics) { window.__qsLoadAnalytics(); }
                    if (window.fbq) { fbq('consent', 'grant'); }
                }
            };
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        .qs-consent { position: fixed; inset-inline: 12px; bottom: 12px; z-index: 60; max-width: 520px; margin-inline: auto; background: var(--surface, #fff); border: 1px solid var(--line, #e5e0d8); border-radius: 14px; padding: 16px 18px; box-shadow: 0 12px 40px rgba(0, 0, 0, .18); display: flex; flex-direction: column; gap: 10px; }
        .qs-consent p { margin: 0; font-size: 14px; color: var(--ink, #333); line-height: 1.7; }
        .qs-consent-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    </style>
@endif
