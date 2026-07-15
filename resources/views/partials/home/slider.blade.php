{{--
    السلايدر الدعائي البارز أعلى الرئيسية (بلوكات CMS من نوع slider/banner).
    كل بلوك = شريحة واحدة: العنوان (title) + النص (content.body) + الصورة (content.image_url)
    + رابط الإجراء (content.url) + نص الزر الاختياري (content.cta). لا حقول مخترعة —
    الحمولة flat KeyValue كما في HomepageBlockResource. Alpine خفيف مضمّن (لا تعديل app.js).
    غياب الصورة يُعالَج بخلفية متدرّجة من ألوان الهوية (لا صورة مخترعة — الدستور 5.3/11.22).
    المتغيّر المتوقّع عبر @include: $slides (Collection من HomepageBlock).
--}}
@once
    @push('head')
        <style>
            .hslider{position:relative;min-height:clamp(280px,42vw,440px);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-l);isolation:isolate;margin-block:clamp(14px,3vw,26px)}
            /* الشرائح متراكبة في نفس الموضع (لا تتكدّس عموديًا فتقفز الصفحة) */
            .hslide{position:absolute;inset:0;display:grid;align-items:end}
            .hslide-media{position:absolute;inset:0;z-index:0;overflow:hidden}
            .hslide-media img{width:100%;height:100%;object-fit:cover;display:block;animation:hs-kenburns 7s ease-out both;will-change:transform}
            @keyframes hs-kenburns{from{transform:scale(1.03)}to{transform:scale(1.12)}}
            @media (prefers-reduced-motion:reduce){.hslide-media img{animation:none}}
            .hslide-fallback{position:absolute;inset:0;z-index:0}
            .hslide::after{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;background:linear-gradient(0deg,rgba(20,10,32,.74),rgba(20,10,32,.18) 55%,rgba(20,10,32,.05))}
            .hslide-body{position:relative;z-index:2;padding:clamp(20px,4vw,46px);max-width:60ch;color:#fff}
            .hslide-body h2{font-size:clamp(24px,4.4vw,44px);font-weight:900;line-height:1.12;letter-spacing:-.6px;text-wrap:balance;text-shadow:0 2px 14px rgba(0,0,0,.5)}
            .hslide-body p{margin-top:12px;font-size:clamp(15px,2vw,19px);line-height:1.6;max-width:52ch;text-shadow:0 1px 10px rgba(0,0,0,.55)}
            .hslide-body .btn{margin-top:20px}
            .hslider-dots{position:absolute;z-index:3;inset-block-end:8px;inset-inline:0;display:flex;gap:2px;justify-content:center}
            .hslider-dot{width:36px;height:36px;display:grid;place-items:center;background:transparent;border:none;cursor:pointer;padding:0}
            .hslider-dot i{width:10px;height:10px;border-radius:50%;border:2px solid rgba(255,255,255,.9);background:transparent;transition:transform .18s,background .18s}
            .hslider-dot[aria-current="true"] i{background:#fff;transform:scale(1.3)}
            .hslider-dot:hover i{background:rgba(255,255,255,.6)}
        </style>
    @endpush
@endonce

<div class="wrap">
    <section class="hslider" aria-label="{{ __('common.brand') }}"
        x-data="{
            i: 0,
            n: {{ $slides->count() }},
            t: null,
            start() {
                if (this.n > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    this.t = setInterval(() => this.next(), 6000);
                }
            },
            stop() { if (this.t) { clearInterval(this.t); this.t = null; } },
            next() { this.i = (this.i + 1) % this.n; },
            go(x) { this.i = x; }
        }"
        x-init="start()"
        @mouseenter="stop()" @mouseleave="start()"
        @focusin="stop()" @focusout="start()">

        @foreach ($slides as $slide)
            @php
                $c = is_array($slide->content) ? $slide->content : [];
                $body = $c['body'] ?? null;

                $rawImg = $c['image_url'] ?? null;
                $img = null;
                if (filled($rawImg)) {
                    $img = \Illuminate\Support\Str::startsWith($rawImg, ['http://', 'https://'])
                        ? $rawImg
                        : (\Illuminate\Support\Str::startsWith($rawImg, 'images/')
                            ? asset($rawImg)
                            : asset('storage/' . ltrim($rawImg, '/')));
                }

                // اسمح فقط بروابط آمنة (http/https أو مسار داخلي) — يمنع javascript: (الدستور 4.2).
                $rawUrl = $c['url'] ?? null;
                $url = (filled($rawUrl) && \Illuminate\Support\Str::startsWith($rawUrl, ['http://', 'https://', '/']))
                    ? $rawUrl
                    : null;

                $ctaLabel = filled($c['cta'] ?? null) ? $c['cta'] : __('home.cta_browse');

                // خلفية بديلة من ألوان الهوية حين لا توجد صورة (لا صورة مخترعة).
                $pairs = [['#6E2FB0', '#EC4E96'], ['#FF8A2A', '#EC4E96'], ['#12B3A6', '#4FB0E8'], ['#6E2FB0', '#12B3A6']];
                $pair = $pairs[$loop->index % count($pairs)];
            @endphp

            <div class="hslide" role="group" aria-label="{{ $slide->title }}"
                x-show="i === {{ $loop->index }}" @unless ($loop->first) style="display:none" @endunless
                x-transition.opacity.duration.700ms>
                @if ($img)
                    <div class="hslide-media">
                        <img src="{{ $img }}" alt="{{ $slide->title }}"
                            loading="{{ $loop->first ? 'eager' : 'lazy' }}" decoding="async"
                            width="1180" height="440">
                    </div>
                @else
                    <div class="hslide-fallback" style="background:linear-gradient(135deg,{{ $pair[0] }},{{ $pair[1] }})"></div>
                @endif

                <div class="hslide-body">
                    @if (filled($slide->title))
                        <h2>{{ $slide->title }}</h2>
                    @endif
                    @if (filled($body))
                        <p>{{ $body }}</p>
                    @endif
                    @if ($url)
                        <a class="btn btn-primary" href="{{ $url }}">{{ $ctaLabel }}</a>
                    @endif
                </div>
            </div>
        @endforeach

        @if ($slides->count() > 1)
            <div class="hslider-dots">
                @foreach ($slides as $slide)
                    <button type="button" class="hslider-dot" @click="go({{ $loop->index }})"
                        :aria-current="i === {{ $loop->index }} ? 'true' : 'false'"
                        aria-label="{{ $slide->title }}"><i aria-hidden="true"></i></button>
                @endforeach
            </div>
        @endif
    </section>
</div>
