{{--
    عرض بلوك CMS واحد من الرئيسية حسب نوعه (text/html/image/cta).
    الحمولة flat KeyValue (body، url، image_url، cta اختياري) كما في HomepageBlockResource.
    أمان: لا نستخدم {!! !!} مع محتوى CMS إطلاقًا (الدستور 4.2/11.8) — حتى نوع html يُعرض
    نصًّا مهروبًا مع الحفاظ على الأسطر (white-space:pre-line). الروابط تُقيَّد بـ http/https أو مسار داخلي.
    المتغيّر المتوقّع عبر @include: $block (نموذج HomepageBlock).
--}}
@once
    @push('head')
        <style>
            .home-block-img{display:block;width:100%;height:auto;border-radius:var(--r-lg);box-shadow:var(--shadow);border:1px solid var(--line)}
        </style>
    @endpush
@endonce

@php
    $c = is_array($block->content) ? $block->content : [];
    $body = $c['body'] ?? null;

    $rawUrl = $c['url'] ?? null;
    $url = (filled($rawUrl) && \Illuminate\Support\Str::startsWith($rawUrl, ['http://', 'https://', '/']))
        ? $rawUrl
        : null;

    $rawImg = $c['image_url'] ?? null;
    $img = null;
    if (filled($rawImg)) {
        $img = \Illuminate\Support\Str::startsWith($rawImg, ['http://', 'https://'])
            ? $rawImg
            : asset('storage/' . ltrim($rawImg, '/'));
    }

    $ctaLabel = filled($c['cta'] ?? null) ? $c['cta'] : __('home.cta_browse');
    $titleId = 'hb-' . $block->id;
@endphp

@switch($block->type)
    @case('cta')
        <section class="sec" style="padding-top:6px">
            <div class="wrap">
                <div class="promo">
                    <div class="promo-grid">
                        <div>
                            @if (filled($block->title))
                                <h3>{{ $block->title }}</h3>
                            @endif
                            @if (filled($body))
                                <p style="white-space:pre-line">{{ $body }}</p>
                            @endif
                            @if ($url)
                                <div class="pbtns">
                                    <a class="btn btn-white" href="{{ $url }}">{{ $ctaLabel }}</a>
                                    <x-wa-button :class="'btn btn-wa'" :label="__('home.cta_whatsapp')" />
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
        @break

    @case('image')
        @if ($img)
            <section class="sec" style="padding-top:6px">
                <div class="wrap">
                    @if ($url)
                        <a href="{{ $url }}">
                            <img class="home-block-img" src="{{ $img }}" alt="{{ $block->title }}" loading="lazy" decoding="async">
                        </a>
                    @else
                        <img class="home-block-img" src="{{ $img }}" alt="{{ $block->title }}" loading="lazy" decoding="async">
                    @endif
                </div>
            </section>
        @endif
        @break

    @default
        {{-- text / html وأي نوع نصّي: قسم قابل للتحرير بأسلوب الرئيسية --}}
        <section class="sec" style="padding-top:6px" @if (filled($block->title)) aria-labelledby="{{ $titleId }}" @endif>
            <div class="wrap">
                <div class="sec-top">
                    @if (filled($block->title))
                        <h2 class="sec-title" id="{{ $titleId }}">{{ $block->title }}</h2>
                    @endif
                    @if (filled($body))
                        <p class="sec-desc" style="white-space:pre-line">{{ $body }}</p>
                    @endif
                    @if ($url)
                        <div style="margin-top:18px">
                            <a class="btn btn-ghost" href="{{ $url }}">{{ $ctaLabel }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
@endswitch
