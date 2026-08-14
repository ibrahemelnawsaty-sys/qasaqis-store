{{-- شريط مشاركة اجتماعيّ عامّ (كتاب/مقال): نسخ الرابط + مشاركة أصليّة (Web Share) + سوشيال
     ظاهر (واتساب/فيسبوك/تليجرام/إكس). يُمرَّر له url/title/text/label. النصوص من اللغة
     (6.4)، RTL + رموز التصميم، Alpine خفيف بلا مكتبات (5.2). الأنماط @once لكل الصفحة. --}}
@props(['url', 'title', 'text', 'label' => null])
@php
    $shareLabel = $label ?? __('book.share.label');
    $encUrl = rawurlencode($url);
    $encText = rawurlencode($text);
    $encTextUrl = rawurlencode($text.' '.$url); // نصّ + رابط (واتساب)
@endphp

@once
    <style>
        .pdp-share{ display:flex; flex-wrap:wrap; align-items:center; gap:8px 12px; margin-top:16px; }
        .pdp-share__label{ font-size:12.5px; font-weight:800; color:var(--ink-soft); }
        .pdp-share__row{ display:flex; flex-wrap:wrap; gap:8px; }
        .pdp-share__btn{ display:inline-grid; place-items:center; width:42px; height:42px; border-radius:12px;
            border:1px solid var(--line); background:var(--surface); color:var(--ink-soft); cursor:pointer;
            text-decoration:none; transition:border-color .15s ease, transform .15s ease, color .15s ease; }
        .pdp-share__btn:hover{ border-color:var(--purple); color:var(--purple); transform:translateY(-2px); }
        .pdp-share__btn:focus-visible{ outline:2px solid var(--purple); outline-offset:2px; }
        .pdp-share__btn.is-copied{ border-color:var(--success); color:var(--success); }
        .pdp-share__toast{ font-size:12px; font-weight:800; color:var(--success); }
        @media (prefers-reduced-motion: reduce){ .pdp-share__btn{ transition:none; } }
    </style>
@endonce

<div class="pdp-share" x-data="{ copied: false, canShare: false }" x-init="canShare = !! (navigator.share)">
    <span class="pdp-share__label">{{ $shareLabel }}</span>

    <div class="pdp-share__row">
        {{-- مشاركة أصليّة (قائمة مشاركة الهاتف) — تظهر إن دعمها المتصفّح فقط. --}}
        <button type="button" class="pdp-share__btn" x-show="canShare" x-cloak
            @click="navigator.share({ title: @js($title), text: @js($text), url: @js($url) }).catch(function () {})"
            aria-label="{{ __('book.share.native') }}" title="{{ __('book.share.native') }}">
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </button>

        {{-- نسخ الرابط + تغذية راجعة (علامة صحّ ثم رجوع لأيقونة الرابط). --}}
        <button type="button" class="pdp-share__btn" :class="copied ? 'is-copied' : ''"
            @click="(navigator.clipboard ? navigator.clipboard.writeText(@js($url)) : Promise.reject()).then(function () { copied = true; setTimeout(function () { copied = false; }, 1800); }).catch(function () {})"
            :aria-label="copied ? @js(__('book.share.copied')) : @js(__('book.share.copy'))"
            :title="copied ? @js(__('book.share.copied')) : @js(__('book.share.copy'))">
            <svg x-show="! copied" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
            <svg x-show="copied" x-cloak viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
        </button>

        {{-- سوشيال ظاهر: يفتح المشاركة برابط المحتوى + رسالة عربية بعنوانه. rel=noopener أمان. --}}
        <a class="pdp-share__btn" href="https://wa.me/?text={{ $encTextUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('book.share.whatsapp') }}"><x-social-icon name="whatsapp" /></a>
        <a class="pdp-share__btn" href="https://www.facebook.com/sharer/sharer.php?u={{ $encUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('book.share.facebook') }}"><x-social-icon name="facebook" /></a>
        <a class="pdp-share__btn" href="https://t.me/share/url?url={{ $encUrl }}&amp;text={{ $encText }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('book.share.telegram') }}"><x-social-icon name="telegram" /></a>
        <a class="pdp-share__btn" href="https://twitter.com/intent/tweet?text={{ $encText }}&amp;url={{ $encUrl }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('book.share.twitter') }}"><x-social-icon name="twitter" /></a>
    </div>

    <span class="pdp-share__toast" role="status" aria-live="polite" x-show="copied" x-cloak x-transition.opacity>✓ {{ __('book.share.copied') }}</span>
</div>
