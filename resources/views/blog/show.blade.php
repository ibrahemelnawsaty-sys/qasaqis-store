@extends('layouts.app')


@php
    $metaTitle = filled($article->seo_title) ? $article->seo_title : $article->title . ' — ' . __('common.brand');
    $metaDesc = filled($article->seo_description)
        ? $article->seo_description
        : (filled($article->excerpt) ? $article->excerpt : __('common.tagline'));

    // غلاف المقال إن وُجد؛ وإلا شعار العلامة (لا نخترع صورة — بند 1.1/0.4).
    $coverSrc = filled($article->cover_image)
        ? (\Illuminate\Support\Str::startsWith($article->cover_image, ['http://', 'https://'])
            ? $article->cover_image
            : (\Illuminate\Support\Str::startsWith($article->cover_image, 'images/')
                ? asset($article->cover_image)
                : asset('storage/' . ltrim($article->cover_image, '/'))))
        : null;
    $ogImage = $coverSrc ?: asset(config('seo.default_image', 'images/logo.png'));

    $publishedAt = $article->published_at;
    $publishedText = $publishedAt?->locale('ar')->translatedFormat('j F Y');

    // content محتوى HTML يحرّره كاتب المحتوى؛ يُمرَّر عبر مطهّر DOM حقيقي يُبقي وسومًا
    // آمنة فقط ويزيل كل السمات (on*/style/href/src) قبل الإخراج عبر {!! !!} (بند 4.2).
    $cleanContent = filled($article->content) ? \App\Support\HtmlSanitizer::clean($article->content) : '';

    // JSON-LD (BlogPosting). array_filter يُسقط القيم الفارغة (كالصورة/الوصف الغائب).
    $ld = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $article->title,
        'description' => $metaDesc,
        'inLanguage' => 'ar',
        'author' => filled($article->author_name)
            ? ['@type' => 'Person', 'name' => $article->author_name]
            : ['@type' => 'Organization', 'name' => __('common.brand')],
        'datePublished' => $publishedAt?->toIso8601String(),
        'dateModified' => $article->updated_at?->toIso8601String(),
        'image' => $coverSrc ? [$coverSrc] : null,
        'mainEntityOfPage' => route('blog.show', $article),
        'articleSection' => $article->category ?: null,
        'publisher' => [
            '@type' => 'Organization',
            'name' => __('common.brand'),
            'logo' => ['@type' => 'ImageObject', 'url' => asset(config('seo.default_image', 'images/logo.png'))],
        ],
    ]);

    $breadcrumbLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('nav.home'), 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('nav.blog'), 'item' => route('blog.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $article->title, 'item' => route('blog.show', $article)],
        ],
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDesc)
@section('og_type', 'article')
@section('og_image', $ogImage)

@push('meta')
    <meta property="article:published_time" content="{{ $publishedAt?->toIso8601String() }}">
    <meta property="article:modified_time" content="{{ $article->updated_at?->toIso8601String() }}">
    @if (filled($article->category))
        <meta property="article:section" content="{{ $article->category }}">
    @endif
    @if (filled($article->author_name))
        <meta property="article:author" content="{{ $article->author_name }}">
    @endif
@endpush

@push('head')
    {{-- أعلام HEX تمنع كسر السياق بـ </script> أو "؛ UNESCAPED_UNICODE يُبقي العربية مقروءة. --}}
    <script type="application/ld+json">{!! json_encode($ld, $jsonFlags) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbLd, $jsonFlags) !!}</script>
@endpush

@section('content')
    <div class="wrap" style="padding-block:clamp(20px,4vw,34px)">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('blog.index') }}">{{ __('nav.blog') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $article->title }}</span>
        </nav>

        <article style="max-width:760px;margin-inline:auto">
            <header style="margin-bottom:18px">
                @if (filled($article->category))
                    <span class="pill">{{ $article->category }}</span>
                @endif

                <h1 style="margin-top:14px;line-height:1.4">{{ $article->title }}</h1>

                <div style="display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:13.5px;color:var(--ink-soft)">
                    @if (filled($article->author_name))
                        <span>✍️ {{ __('blog.by_author', ['name' => $article->author_name]) }}</span>
                    @endif
                    @if ($publishedText)
                        <span>📅 {{ $publishedText }}</span>
                    @endif
                    <span>⏱️ {{ trans_choice('blog.read_minutes', (int) $article->reading_minutes, ['count' => (int) $article->reading_minutes]) }}</span>
                </div>
            </header>

            @if ($coverSrc)
                <img src="{{ $coverSrc }}" alt="{{ __('blog.cover_alt', ['title' => $article->title]) }}"
                    loading="lazy" decoding="async" width="760" height="428"
                    style="width:100%;height:auto;border-radius:18px;margin-bottom:22px;object-fit:cover">
            @endif

            @if ($cleanContent !== '')
                <div class="article-content" style="font-size:16px;line-height:2;color:var(--ink)">{!! $cleanContent !!}</div>
            @elseif (filled($article->excerpt))
                <p style="font-size:16px;line-height:2;color:var(--ink)">{{ $article->excerpt }}</p>
            @endif
        </article>

        {{-- كتب ذُكرت في المقال (روابط داخلية + ترويج + SEO) — بطاقات الكتاب القياسية. --}}
        @if ($article->books->isNotEmpty())
            <section class="sec" aria-labelledby="blog-books">
                <div class="sec-top" style="text-align:start;max-width:none">
                    <h2 class="sec-title" id="blog-books" style="font-size:24px">{{ __('blog.mentioned_books_title') }}</h2>
                    <p class="sec-desc">{{ __('blog.mentioned_books_desc') }}</p>
                </div>
                <div class="shelf">
                    @foreach ($article->books as $book)
                        <x-book-card :book="$book" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- مقالات ذات صلة من نفس القسم. --}}
        @if ($related->isNotEmpty())
            @php
                $blogPalettes = [
                    ['#6E2FB0', '#EC4E96'], ['#EC4E96', '#FF8A2A'], ['#12B3A6', '#4FB0E8'],
                    ['#FF8A2A', '#FFC23C'], ['#6E2FB0', '#12B3A6'], ['#4FB0E8', '#12B3A6'],
                ];
            @endphp
            <section class="sec" style="padding-top:6px" aria-labelledby="blog-related">
                <div class="sec-top" style="text-align:start;max-width:none">
                    <h2 class="sec-title" id="blog-related" style="font-size:24px">{{ __('blog.related_title') }}</h2>
                    <p class="sec-desc">{{ __('blog.related_desc') }}</p>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(250px,30%,330px),1fr));gap:clamp(16px,2.4vw,24px)">
                    @foreach ($related as $rel)
                        @php
                            $pair = $blogPalettes[(int) $rel->id % count($blogPalettes)];
                            $relCover = filled($rel->cover_image)
                                ? (\Illuminate\Support\Str::startsWith($rel->cover_image, ['http://', 'https://'])
                                    ? $rel->cover_image
                                    : asset('storage/' . ltrim($rel->cover_image, '/')))
                                : null;
                            $relUrl = route('blog.show', $rel);
                        @endphp
                        <article style="display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--line, rgba(0,0,0,.06));border-radius:18px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.05)">
                            <a href="{{ $relUrl }}"
                                style="display:block;position:relative;aspect-ratio:16/10;background:linear-gradient(150deg,{{ $pair[0] }},{{ $pair[1] }})"
                                aria-label="{{ $rel->title }}">
                                @if ($relCover)
                                    <img src="{{ $relCover }}" alt="{{ __('blog.cover_alt', ['title' => $rel->title]) }}"
                                        loading="lazy" decoding="async" width="360" height="225"
                                        style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'">
                                @else
                                    <span aria-hidden="true"
                                        style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:40px">📖</span>
                                @endif
                            </a>
                            <div style="display:flex;flex-direction:column;gap:8px;padding:16px;flex:1">
                                <span style="font-size:12.5px;color:var(--ink-soft)">⏱️ {{ trans_choice('blog.read_minutes', (int) $rel->reading_minutes, ['count' => (int) $rel->reading_minutes]) }}</span>
                                <a href="{{ $relUrl }}"
                                    style="font-weight:800;font-size:16px;line-height:1.5;color:var(--ink);text-decoration:none">{{ $rel->title }}</a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <div style="text-align:center;margin-top:26px">
            <a class="btn btn-ghost" href="{{ route('blog.index') }}">← {{ __('blog.back_to_blog') }}</a>
        </div>
    </div>
@endsection
