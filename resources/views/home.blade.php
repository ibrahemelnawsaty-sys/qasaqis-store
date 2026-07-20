@extends('layouts.app')


@php
    // نصوص الهيرو قابلة للتحرير من إعدادات المتجر (CMS، الدستور 0.8) مع رجوع
    // آمن لنصوص الترجمة حين تكون قيمة الإعداد فارغة أو غير مُحمَّلة بعد.
    $heroTitle = filled($storeSettings['hero_title'] ?? null) ? $storeSettings['hero_title'] : null;
    $heroSub = filled($storeSettings['hero_subtitle'] ?? null) ? $storeSettings['hero_subtitle'] : __('home.hero_sub');
@endphp

{{-- SEO الرئيسية: يكفي دفع الوصف — التخطيط يشتقّ منه og:description و twitter:description
     ويُصدر og:type/og:site_name/og:title/og:locale وبطاقة تويتر بنفسه (app.blade.php:47-64).
     كانت هذه الصفحة تعيد دفع الكتلة كاملة فتنبعث الوسوم مرّتين، وبقيمتَي og:locale
     متعارضتين (ar_AR من التخطيط و ar من هنا). النصوص من CMS/الترجمة (الدستور 6.4). --}}
@section('meta_description', $heroSub)

@section('content')
    @php
        $totalBooks = $categories->sum('books_count');
        $totalCategories = $categories->count();
        $softBg = ['var(--purple-soft)', 'var(--teal-soft)', 'var(--orange-soft)', 'var(--gold)', 'var(--pink-soft)', 'var(--teal-soft)'];
    @endphp

    {{-- السلايدر الدعائي البارز (بلوكات CMS slider/banner). عند غيابها نعرض الهيرو الافتراضي المبهج. --}}
    <x-section-band :pattern="$sectionPatterns['hero'] ?? ''">
    @if ($slides->isNotEmpty())
        {{-- عنوان رئيسي مخفيّ بصريًا: السلايدر لا يحمل <h1>، والهيرو (صاحب الـ <h1> الوحيد)
             لا يُعرض حين توجد شرائح — فكانت الرئيسية تُصيَّر بلا <h1> إطلاقًا. هذا يكسر
             تسلسل العناوين للوصولية، ويُفقد Google إحدى إشارات «اسم الموقع». --}}
        <h1 class="sr-only">{{ __('common.brand') }} — {{ __('common.tagline') }}</h1>
        @include('partials.home.slider', ['slides' => $slides])
    @endif

    {{-- HERO الافتراضي — يظهر حين لا توجد شرائح CMS (لا صفحة فارغة أبدًا) --}}
    @if ($slides->isEmpty())
    <div class="hero">
        <span class="blob drift" style="width:220px;height:220px;background:var(--teal-soft);top:-40px;inset-inline-start:-60px" aria-hidden="true"></span>
        <span class="blob drift s2" style="width:160px;height:160px;background:var(--pink-soft);bottom:20px;inset-inline-end:8%" aria-hidden="true"></span>
        <span class="blob drift s3" style="width:120px;height:120px;background:var(--gold);opacity:.25;top:30px;inset-inline-end:30%" aria-hidden="true"></span>
        <div class="wrap">
            <div class="hero-grid">
                <div>
                    <span class="badge-happy">{{ __('home.hero_badge') }}</span>
                    <h1 class="hero-title">
                        @if (filled($heroTitle))
                            {{ $heroTitle }}
                        @else
                            {{ __('home.hero_title_before') }}
                            <span class="w">{{ __('home.hero_title_word') }}</span>
                            {{ __('home.hero_title_after') }}
                            <span class="u">{{ __('home.hero_title_underline') }}</span>
                            {{ __('home.hero_title_emoji') }}
                        @endif
                    </h1>
                    <p class="hero-sub">{{ $heroSub }}</p>
                    <div class="hero-cta">
                        <a class="btn btn-primary" href="{{ route('books.index') }}">{{ __('home.cta_browse') }}</a>
                        <x-wa-button :class="'btn btn-wa'" :label="__('home.cta_whatsapp')" />
                    </div>
                    <div class="hero-stats">
                        <div class="hs"><span class="n">+{{ $totalBooks }}</span><span class="l">{{ __('home.stat_books_label') }}</span></div>
                        <div class="hs-div"></div>
                        <div class="hs"><span class="n">{{ $totalCategories }}</span><span class="l">{{ __('home.stat_categories_label') }}</span></div>
                        <div class="hs-div"></div>
                        <div class="hs"><span class="n">{{ __('home.stat_moms_value') }}</span><span class="l">{{ __('home.stat_moms_label') }}</span></div>
                    </div>
                </div>
                <div class="hero-art">
                    <div class="art-card">
                        <span class="sticker drift" style="top:16px;inset-inline-start:22px" aria-hidden="true">⭐</span>
                        <span class="sticker drift s2" style="top:40px;inset-inline-end:30px;font-size:24px" aria-hidden="true">☁️</span>
                        <span class="sticker drift s3" style="bottom:26px;inset-inline-end:24px" aria-hidden="true">🎈</span>
                        <span class="sticker drift" style="bottom:60px;inset-inline-start:20px;font-size:22px" aria-hidden="true">✏️</span>
                        <svg viewBox="0 0 300 300" width="76%" role="img" aria-label="{{ __('home.hero_art_alt') }}">
                            <ellipse cx="150" cy="252" rx="118" ry="20" fill="rgba(0,0,0,.06)"/>
                            <g>
                                <rect x="58" y="196" width="184" height="34" rx="9" fill="#12B3A6"/>
                                <rect x="58" y="196" width="14" height="34" fill="rgba(0,0,0,.15)"/>
                                <rect x="86" y="205" width="120" height="6" rx="3" fill="rgba(255,255,255,.7)"/>
                            </g>
                            <g transform="rotate(-4 150 175)">
                                <rect x="66" y="158" width="168" height="34" rx="9" fill="#EC4E96"/>
                                <rect x="66" y="158" width="14" height="34" fill="rgba(0,0,0,.15)"/>
                                <rect x="92" y="167" width="110" height="6" rx="3" fill="rgba(255,255,255,.7)"/>
                            </g>
                            <g transform="rotate(3 150 138)">
                                <rect x="72" y="120" width="156" height="34" rx="9" fill="#FF8A2A"/>
                                <rect x="72" y="120" width="14" height="34" fill="rgba(0,0,0,.15)"/>
                                <rect x="96" y="129" width="100" height="6" rx="3" fill="rgba(255,255,255,.7)"/>
                            </g>
                            <g transform="translate(0,-6)">
                                <path d="M150 66 C120 50 92 52 78 60 L78 116 C92 108 120 106 150 122 Z" fill="#6E2FB0"/>
                                <path d="M150 66 C180 50 208 52 222 60 L222 116 C208 108 180 106 150 122 Z" fill="#7d3ac2"/>
                                <path d="M150 66 L150 122" stroke="rgba(255,255,255,.5)" stroke-width="2"/>
                                <circle cx="150" cy="44" r="12" fill="#FFC23C"/>
                                <path d="M150 32 L150 20 M150 68 L150 78 M128 44 L116 44 M172 44 L184 44" stroke="#FFC23C" stroke-width="3" stroke-linecap="round"/>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    </x-section-band>
    {{-- نهاية الهيرو الافتراضي --}}

    <svg class="wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true">
        <path d="M0,40 C240,90 480,90 720,55 C960,20 1200,20 1440,50 L1440,90 L0,90 Z" fill="var(--purple-soft)"/>
    </svg>

    <div class="band band-lilac {{ $sectionPatterns['trust'] ?? '' }}">
        <div class="wrap">
            {{-- TRUST — محتوى قابل للتحرير من الأدمن (جدول trust_items). rescue تُرجع null
                 لو الجدول غير موجود (قبل الهجرة) فنرجع لقيم ملف اللغة؛ ومصفوفة فارغة تعني
                 أن الأدمن حذف كل العناصر عمدًا فلا نعرض الشريط. --}}
            @php
                $trustItems = rescue(
                    fn () => \App\Models\TrustItem::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get()
                        ->map(fn ($t) => ['icon' => $t->icon, 'title' => $t->title, 'desc' => $t->description])
                        ->all(),
                    null,
                    report: false,
                );

                if ($trustItems === null) {
                    $trustItems = __('home.trust');
                }
            @endphp
            @if (filled($trustItems))
                <div class="trust">
                    @foreach ($trustItems as $item)
                        <div class="trust-item">
                            <span class="e" style="background:var(--purple-soft);color:var(--purple)" aria-hidden="true"><x-ui-icon :name="$item['icon'] ?? 'badge-check'" :size="26" /></span>
                            <div>
                                <div class="t">{{ $item['title'] }}</div>
                                <div class="d">{{ $item['desc'] ?? '' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- CATEGORIES --}}
            <x-section-band :pattern="$sectionPatterns['categories'] ?? ''">
            <section class="sec" style="padding-top:6px" aria-labelledby="cats-title">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.categories_eyebrow') }}</span>
                    <h2 class="sec-title" id="cats-title">{{ __('home.categories_title') }}</h2>
                    <p class="sec-desc">{{ __('home.categories_desc') }}</p>
                </div>
                <div class="cats">
                    @foreach ($categories as $i => $cat)
                        @php
                            $bg = filled($cat->color_hex)
                                ? 'color-mix(in srgb,' . $cat->color_hex . ' 16%, var(--surface))'
                                : $softBg[$i % count($softBg)];
                        @endphp
                        <a class="cat {{ $cat->books_count === 0 ? 'empty' : '' }}"
                            href="{{ route('categories.show', $cat) }}" style="background:{{ $bg }}">
                            <span class="ce" aria-hidden="true"><x-category-icon :cat="$cat" /></span>
                            {{-- العنوان في سطر مستقل، وعدد الكتب في سطر تحته (block بدل inline) --}}
                            <span class="cn" style="display:block">{{ $cat->name }}</span>
                            <span class="cc" style="display:block">
                                {{ $cat->books_count > 0 ? trans_choice('nav.books_count', $cat->books_count, ['count' => $cat->books_count]) : __('nav.coming_soon') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
            </x-section-band>
        </div>
    </div>

    <svg class="wave" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true" style="transform:rotate(180deg)">
        <path d="M0,40 C240,90 480,90 720,55 C960,20 1200,20 1440,50 L1440,90 L0,90 Z" fill="var(--purple-soft)"/>
    </svg>

    {{-- FEATURED --}}
    @if ($featured->isNotEmpty())
        <x-section-band :pattern="$sectionPatterns['featured'] ?? ''">
        <section class="sec" aria-labelledby="feat-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.featured_eyebrow') }}</span>
                    <h2 class="sec-title" id="feat-title">{{ __('home.featured_title') }}</h2>
                    <p class="sec-desc">{{ __('home.featured_desc') }}</p>
                </div>
                <div class="shelf">
                    @foreach ($featured as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
                <div style="text-align:center;margin-top:32px">
                    <a class="btn btn-ghost" href="{{ route('books.index') }}">{{ __('home.view_all') }}</a>
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- BESTSELLERS «الأكثر مبيعًا» — يظهر فقط عند وجود كتب (المتحكّم يوفّر رجوعًا
         للأكثر مشاهدة/الأحدث فلا يكون القسم فارغًا). شبكة بطاقات مثل المميّزة. --}}
    @if ($bestsellers->isNotEmpty())
        <x-section-band :pattern="$sectionPatterns['bestsellers'] ?? ''">
        <section class="sec" style="padding-top:6px" aria-labelledby="best-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.bestsellers_eyebrow') }}</span>
                    <h2 class="sec-title" id="best-title">{{ __('home.bestsellers_title') }}</h2>
                    <p class="sec-desc">{{ __('home.bestsellers_desc') }}</p>
                </div>
                <div class="shelf">
                    @foreach ($bestsellers as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
                <div style="text-align:center;margin-top:32px">
                    <a class="btn btn-ghost" href="{{ route('books.index') }}">{{ __('home.view_all') }}</a>
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- بلوكات CMS القابلة للتحرير (نصوص/صور/بانرات عرض) بالترتيب.
         عند غيابها نعرض بانر العرض الافتراضي المبهج (لا فراغ). --}}
    @forelse ($blocks as $block)
        {{-- نقش اختياري تختاره الأدمن لكل كتلة من داخل شاشة كتل الرئيسية. --}}
        <x-section-band :pattern="\App\Enums\BackgroundPattern::fromValue($block->background_pattern)->cssClass() ?? ''">
            @include('partials.home.block', ['block' => $block])
        </x-section-band>
    @empty
        {{-- PROMO الافتراضي --}}
        <x-section-band :pattern="$sectionPatterns['promo'] ?? ''">
        <section class="sec" style="padding-top:6px">
            <div class="wrap">
                <div class="promo">
                    <div class="promo-grid">
                        <div>
                            <span class="badge-happy" style="background:#fff;color:var(--purple)">{{ __('home.promo_badge') }}</span>
                            <h3>{{ __('home.promo_title') }}</h3>
                            <p>{{ __('home.promo_desc') }}</p>
                            <div class="pbtns">
                                <a class="btn btn-white" href="{{ route('books.offers') }}">{{ __('home.promo_cta') }}</a>
                                <x-wa-button :class="'btn btn-wa'" :label="__('home.cta_whatsapp')" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        </x-section-band>
    @endforelse

    {{-- LATEST --}}
    @if ($latest->isNotEmpty())
        <x-section-band :pattern="$sectionPatterns['latest'] ?? ''">
        <section class="sec" aria-labelledby="latest-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.latest_eyebrow') }}</span>
                    <h2 class="sec-title" id="latest-title">{{ __('home.latest_title') }}</h2>
                    <p class="sec-desc">{{ __('home.latest_desc') }}</p>
                </div>
                <div class="shelf">
                    @foreach ($latest as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- WHY MOMS — بطاقات من قاعدة البيانات (جدول why_items). rescue تُرجع null قبل
         الهجرة فنرجع لقيم ملف اللغة؛ ومصفوفة فارغة تعني حذف الأدمن كلَّها فنخفي القسم. --}}
    @php
        $whyItems = rescue(
            fn () => \App\Models\WhyItem::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($w) => ['emoji' => $w->icon, 'title' => $w->title, 'desc' => $w->description])
                ->all(),
            null,
            report: false,
        );

        if ($whyItems === null) {
            $whyItems = __('home.why');
        }
    @endphp
    @if (filled($whyItems))
        <x-section-band :pattern="$sectionPatterns['why'] ?? ''">
        <section class="sec" style="padding-top:6px" aria-labelledby="why-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.why_eyebrow') }}</span>
                    <h2 class="sec-title" id="why-title">{{ __('home.why_title') }}</h2>
                </div>
                <div class="why">
                    @foreach ($whyItems as $i => $card)
                        <div class="why-card">
                            {{-- اللون يتناوب عبر nth-child في app.css، فلا حاجة لنمط سطري --}}
                            <div class="we" aria-hidden="true"><x-why-icon :name="$card['emoji']" /></div>
                            <h4>{{ $card['title'] }}</h4>
                            <p>{{ $card['desc'] ?? '' }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- شهادات العملاء (صور) — من قاعدة البيانات (جدول feedback_images) ليضيف الأدمن صورًا.
         rescue تُرجع null قبل الهجرة فنرجع للصور الساكنة التسع؛ ومصفوفة فارغة تعني حذف
         الأدمن كلَّها فنخفي القسم. --}}
    @php
        $feedbackImages = rescue(
            fn () => \App\Models\FeedbackImage::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($f) => ['url' => $f->url, 'alt' => $f->alt])
                ->all(),
            null,
            report: false,
        );

        if ($feedbackImages === null) {
            $feedbackImages = collect(range(1, 9))
                ->map(fn ($n) => [
                    'url' => asset('images/reviews/review-'.$n.'.webp'),
                    'alt' => __('home.feedback_alt', ['n' => $n]),
                ])
                ->all();
        }
    @endphp
    @if (filled($feedbackImages))
        <x-section-band :pattern="$sectionPatterns['feedback'] ?? ''">
        <section class="sec" aria-labelledby="feedback-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.feedback_eyebrow') }}</span>
                    <h2 class="sec-title" id="feedback-title">{{ __('home.feedback_title') }}</h2>
                    <p class="sec-desc">{{ __('home.feedback_desc') }}</p>
                </div>
                <div class="reviews-gallery" role="list">
                    @foreach ($feedbackImages as $img)
                        <figure class="rev-card" role="listitem">
                            <img src="{{ $img['url'] }}"
                                alt="{{ filled($img['alt'] ?? null) ? $img['alt'] : __('home.feedback_alt', ['n' => $loop->iteration]) }}"
                                loading="lazy" decoding="async" width="640" height="537">
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- طلبات الجملة والاستفسارات + الموقع --}}
    <x-section-band :pattern="$sectionPatterns['bulk'] ?? ''">
    <section class="sec" aria-labelledby="bulk-title">
        <div class="wrap">
            <div class="sec-top">
                <span class="sec-eyebrow">{{ __('home.bulk_eyebrow') }}</span>
                <h2 class="sec-title" id="bulk-title">{{ __('home.bulk_title') }}</h2>
                <p class="sec-desc">{{ __('home.bulk_desc') }}</p>
            </div>
            @once
                @push('head')
                    <style>
                        .inq-label{display:grid;gap:5px;font-size:13px;font-weight:700;color:var(--ink)}
                        .inq-input{width:100%;min-height:46px;border:1.5px solid var(--line);border-radius:var(--r-sm);background:var(--surface-soft);padding:10px 14px;font-family:inherit;font-size:14.5px;color:var(--ink);outline:none}
                        .inq-input:focus{border-color:var(--purple)}
                        textarea.inq-input{min-height:110px;resize:vertical;line-height:1.6}
                        .inq-err{color:var(--pink);font-size:12.5px;font-weight:700}
                        .inq-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
                        .inq-grid{display:grid;grid-template-columns:1.35fr 1fr;gap:18px;align-items:start}
                        @media (max-width:760px){.inq-grid,.inq-row{grid-template-columns:1fr}}
                    </style>
                @endpush
            @endonce
            <div class="inq-grid">
                {{-- نموذج الاستفسار (يُحفظ في قاعدة البيانات ويظهر للأدمن/الدعم) --}}
                <div id="inquiry" style="background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:24px;box-shadow:var(--shadow-s)">
                    @if (session('inquiry_success'))
                        <div role="status" style="background:color-mix(in srgb,var(--good) 14%,var(--surface));border:1px solid color-mix(in srgb,var(--good) 40%,transparent);border-radius:var(--r-md);padding:14px 16px;margin-bottom:16px;font-weight:800">✅ {{ __('home.inquiry_success') }}</div>
                    @endif
                    <form method="post" action="{{ route('inquiry.store') }}" style="display:grid;gap:13px">
                        @csrf
                        {{-- مصيدة سبام مخفية — إخفاء بلا إزاحة سالبة (left:-9999 كان يسبب
                             تمريرًا أفقيًا لا نهائيًا في RTL لأن html بلا overflow-x:hidden). --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);opacity:0;pointer-events:none">
                        <div class="inq-row">
                            <label class="inq-label">{{ __('home.inq_type') }}
                                <select name="type" class="inq-input">
                                    <option value="contact" @selected(old('type') === 'contact')>{{ __('home.inq_type_contact') }}</option>
                                    <option value="wholesale_b2b" @selected(old('type') === 'wholesale_b2b')>{{ __('home.inq_type_bulk') }}</option>
                                    <option value="product_question" @selected(old('type') === 'product_question')>{{ __('home.inq_type_product') }}</option>
                                    <option value="complaint" @selected(old('type') === 'complaint')>{{ __('home.inq_type_complaint') }}</option>
                                </select>
                            </label>
                            <label class="inq-label">{{ __('home.inq_name') }}
                                <input type="text" name="name" value="{{ old('name') }}" maxlength="150" required class="inq-input">
                            </label>
                        </div>
                        <div class="inq-row">
                            <label class="inq-label">{{ __('home.inq_phone') }}
                                <input type="tel" name="phone" value="{{ old('phone') }}" maxlength="20" required class="inq-input" dir="ltr">
                            </label>
                            <label class="inq-label">{{ __('home.inq_email') }}
                                <input type="email" name="email" value="{{ old('email') }}" maxlength="191" class="inq-input" dir="ltr">
                            </label>
                        </div>
                        <label class="inq-label">{{ __('home.inq_message') }}
                            <textarea name="message" maxlength="2000" required class="inq-input">{{ old('message') }}</textarea>
                        </label>
                        @if ($errors->any())
                            <div class="inq-err">{{ $errors->first() }}</div>
                        @endif
                        <button type="submit" class="btn btn-primary">{{ __('home.inq_submit') }}</button>
                    </form>
                </div>
                {{-- واتساب سريع + الموقع --}}
                <div style="display:grid;gap:14px">
                    <div style="background:linear-gradient(150deg,var(--purple),var(--pink));color:#fff;border-radius:var(--r-lg);padding:22px;text-align:center;box-shadow:var(--shadow)">
                        <div style="font-size:36px;line-height:1" aria-hidden="true">💬</div>
                        <p style="opacity:.95;font-size:14px;margin:8px 0 14px">{{ __('home.bulk_desc') }}</p>
                        <x-wa-button :class="'btn btn-white btn-block'" :label="__('home.bulk_wa')" />
                    </div>
                    @if (filled($storeSettings['contact_address'] ?? '') || filled($storeSettings['store_maps_url'] ?? ''))
                        <div style="background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:22px;text-align:center;box-shadow:var(--shadow-s)">
                            <div style="font-size:36px;line-height:1" aria-hidden="true">📍</div>
                            <h3 style="font-weight:900;font-size:18px;margin:8px 0 4px">{{ __('home.bulk_visit_title') }}</h3>
                            @if (filled($storeSettings['contact_address'] ?? ''))
                                <p style="font-weight:800;color:var(--purple);font-size:14px;margin-bottom:14px">🗺️ {{ $storeSettings['contact_address'] }}</p>
                            @endif
                            @if (filled($storeSettings['store_maps_url'] ?? ''))
                                <a class="btn btn-ghost btn-block" href="{{ $storeSettings['store_maps_url'] }}" target="_blank" rel="noopener">{{ __('home.bulk_map_cta') }}</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
    </x-section-band>

    {{-- REVIEWS (بيانات حقيقية فقط) --}}
    @if ($reviews->isNotEmpty())
        <x-section-band :pattern="$sectionPatterns['reviews'] ?? ''">
        <section class="sec" style="padding-top:6px" aria-labelledby="rev-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('home.reviews_eyebrow') }}</span>
                    <h2 class="sec-title" id="rev-title">{{ __('home.reviews_title') }}</h2>
                </div>
                <div class="reviews">
                    @foreach ($reviews as $review)
                        <div class="review">
                            <div class="stars" aria-hidden="true">{{ str_repeat('★', (int) $review->rating) }}{{ str_repeat('☆', max(0, 5 - (int) $review->rating)) }}</div>
                            @if (filled($review->body))
                                <p>«{{ $review->body }}»</p>
                            @endif
                            <div class="who">
                                <span class="av" aria-hidden="true">{{ mb_substr($review->author_name ?? '؟', 0, 1) }}</span>
                                <div>
                                    <div class="nm">{{ $review->author_name }}</div>
                                    @if ($review->book)
                                        <div class="role">{{ $review->book->title }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        </x-section-band>
    @endif

    {{-- أحدث المقالات (المدونة) — يظهر فقط إن وُجدت مقالات منشورة. زر «كل المقالات»
         يقود إلى فهرس المدونة. بطاقات خفيفة بعنصر بديل للمقال بلا غلاف (لا صورة مخترعة). --}}
    @if ($articles->isNotEmpty())
        @php
            $blogPalettes = [
                ['#6E2FB0', '#EC4E96'], ['#EC4E96', '#FF8A2A'], ['#12B3A6', '#4FB0E8'],
                ['#FF8A2A', '#FFC23C'], ['#6E2FB0', '#12B3A6'], ['#4FB0E8', '#12B3A6'],
            ];
        @endphp
        <x-section-band :pattern="$sectionPatterns['blog_latest'] ?? ''">
        <section class="sec" style="padding-top:6px" aria-labelledby="blog-latest-title">
            <div class="wrap">
                <div class="sec-top">
                    <span class="sec-eyebrow">{{ __('blog.home_eyebrow') }}</span>
                    <h2 class="sec-title" id="blog-latest-title">{{ __('blog.home_title') }}</h2>
                    <p class="sec-desc">{{ __('blog.home_desc') }}</p>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(250px,30%,330px),1fr));gap:clamp(16px,2.4vw,24px)">
                    @foreach ($articles as $article)
                        @php
                            $pair = $blogPalettes[(int) $article->id % count($blogPalettes)];
                            $coverSrc = filled($article->cover_image)
                                ? (\Illuminate\Support\Str::startsWith($article->cover_image, ['http://', 'https://'])
                                    ? $article->cover_image
                                    : (\Illuminate\Support\Str::startsWith($article->cover_image, 'images/')
                                        ? asset($article->cover_image)
                                        : asset('storage/' . ltrim($article->cover_image, '/'))))
                                : null;
                            $articleUrl = route('blog.show', $article);
                        @endphp
                        <article style="display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--line, rgba(0,0,0,.06));border-radius:18px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.05)">
                            <a href="{{ $articleUrl }}"
                                style="display:block;position:relative;aspect-ratio:16/10;background:linear-gradient(150deg,{{ $pair[0] }},{{ $pair[1] }})"
                                aria-label="{{ $article->title }}">
                                @if ($coverSrc)
                                    <img src="{{ $coverSrc }}" alt="{{ __('blog.cover_alt', ['title' => $article->title]) }}"
                                        loading="lazy" decoding="async" width="360" height="225"
                                        style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                                @else
                                    <span aria-hidden="true"
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:40px">📖</span>
                                @endif
                                @if (filled($article->category))
                                    <span style="position:absolute;inset-block-start:12px;inset-inline-start:12px;background:rgba(255,255,255,.92);color:var(--purple);font-weight:800;font-size:12px;padding:4px 10px;border-radius:999px">{{ $article->category }}</span>
                                @endif
                            </a>
                            <div style="display:flex;flex-direction:column;gap:8px;padding:16px;flex:1">
                                <span style="font-size:12.5px;color:var(--ink-soft)">⏱️ {{ trans_choice('blog.read_minutes', (int) $article->reading_minutes, ['count' => (int) $article->reading_minutes]) }}</span>
                                <a href="{{ $articleUrl }}"
                                    style="font-weight:800;font-size:16px;line-height:1.5;color:var(--ink);text-decoration:none">{{ $article->title }}</a>
                                @if (filled($article->excerpt))
                                    <p style="font-size:13.5px;color:var(--ink-soft);line-height:1.7;margin:0">{{ \Illuminate\Support\Str::limit($article->excerpt, 110) }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div style="text-align:center;margin-top:32px">
                    <a class="btn btn-ghost" href="{{ route('blog.index') }}">{{ __('blog.home_view_all') }}</a>
                </div>
            </div>
        </section>
        </x-section-band>
    @endif
@endsection
