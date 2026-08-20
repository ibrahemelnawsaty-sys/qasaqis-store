@props([
    'book',
    'variant' => 'card', // card | pdp
    'href' => null,
    'soldOut' => false,  // نفذت الكمية: تغبيش الغلاف + ختم «نفذ»
])

@php
    // On-brand neutral placeholder gradients (BOOK10 has no cover on disk — 0.4/11.22:
    // never fabricate a cover image; show a neutral branded tile with the title).
    $palettes = [
        ['#6E2FB0', '#EC4E96'],
        ['#EC4E96', '#FF8A2A'],
        ['#12B3A6', '#4FB0E8'],
        ['#FF8A2A', '#FFC23C'],
        ['#6E2FB0', '#12B3A6'],
        ['#4FB0E8', '#12B3A6'],
    ];
    $pair = $palettes[(int) ($book->id ?? 0) % count($palettes)];

    // رابط الغلاف موسومًا بالعلامة المائية (عبر مسار بالمعرّف, لا يكشف /storage).
    // للبطاقة: src خفيف (نسخة 640px) + srcset متجاوب (320/640) فيسحب المتصفّح المقاس
    // المناسب للجهاز (~12-28KB بدل ~67-230KB). صفحة الكتاب (pdp) تبقى بالنسخة الكاملة.
    $isCard = $variant !== 'pdp';
    $src = $isCard ? $book->cardCoverUrl() : $book->coverUrl();
    $srcset = $isCard ? $book->coverSrcset() : null;
    $sizes = '(max-width: 720px) 50vw, 280px';

    $coverClass = $variant === 'pdp' ? 'pdp-cover' : 'cover';
    $coverClass .= $soldOut ? ' is-soldout' : '';
    $style = 'background:linear-gradient(150deg,' . $pair[0] . ',' . $pair[1] . ')';
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $coverClass }}" style="{{ $style }}">
        {{ $slot }}
        @if ($src)
            <img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $book->title }}" loading="lazy" decoding="async" width="360" height="440" onerror="this.style.display='none'">
        @else
            <span class="ctitle">{{ $book->title }}</span>
        @endif
        @if ($soldOut)
            <span class="soldout-ov" aria-hidden="true"><span class="soldout-ov__tag">{{ __('common.sold_out') }}</span></span>
        @endif
    </a>
@else
    <div class="{{ $coverClass }}" style="{{ $style }}">
        {{ $slot }}
        @if ($src)
            <img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif alt="{{ $book->title }}" loading="lazy" decoding="async" width="360" height="440" onerror="this.style.display='none'">
        @else
            <span class="ctitle">{{ $book->title }}</span>
        @endif
        @if ($soldOut)
            <span class="soldout-ov" aria-hidden="true"><span class="soldout-ov__tag">{{ __('common.sold_out') }}</span></span>
        @endif
    </div>
@endif

@once
    @push('head')
        {{-- طبقة «نفذت الكمية»: تغبيش الغلاف (blur + رماديّ) وختمٌ أحمر مائل فوقه. مضمّنة
             كـ<style> مرّة واحدة للصفحة (الخادم بلا npm)؛ تعمل على البطاقة وصفحة الكتاب. --}}
        <style>
            .cover.is-soldout img, .cover.is-soldout .ctitle,
            .pdp-cover.is-soldout img, .pdp-cover.is-soldout .ctitle{
                filter:blur(3px) grayscale(.6); opacity:.72; transform:scale(1.06);
            }
            .soldout-ov{ position:absolute; inset:0; z-index:5; display:grid; place-items:center;
                background:rgba(38,18,60,.26); }
            .soldout-ov__tag{ font-weight:900; font-size:15px; letter-spacing:.5px; color:#fff;
                background:linear-gradient(135deg,#a01f4d,#c0392b); padding:7px 20px; border-radius:999px;
                box-shadow:0 10px 24px -8px rgba(0,0,0,.55); transform:rotate(-7deg);
                border:2px solid rgba(255,255,255,.85); }
            .pdp-cover.is-soldout .soldout-ov__tag{ font-size:19px; padding:10px 28px; }
        </style>
    @endpush
@endonce
