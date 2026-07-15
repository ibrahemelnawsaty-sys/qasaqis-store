@php($ax = $analytics ?? [])
@if (($ax['enabled'] ?? false) && filled($ax['meta_pixel_id'] ?? null))
    <noscript><img height="1" width="1" style="display:none" alt=""
            src="https://www.facebook.com/tr?id={{ $ax['meta_pixel_id'] }}&ev=PageView&noscript=1"></noscript>
@endif
