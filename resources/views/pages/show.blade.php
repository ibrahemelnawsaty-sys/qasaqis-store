@extends('layouts.app')

{{-- نقش خلفية صفحة ثابتة من الـCMS --}}
@section('body_class', 'pat-calligraphic-curls')

@php
    // SEO يُدار من الـ CMS (SeoMeta) مع الرجوع لعنوان الصفحة وشعار العلامة عند غيابه.
    $metaTitle = $page->seo?->meta_title ?: $page->title . ' — ' . __('common.brand');
    $metaDesc = $page->seo?->meta_description ?: __('common.tagline');
    $robots = $page->seo?->robots ?: 'index,follow';
    $canonical = filled($page->seo?->canonical_url) ? $page->seo->canonical_url : null;

    $ogTitle = $page->seo?->og_title ?: $metaTitle;
    $ogDesc = $page->seo?->og_description ?: $metaDesc;
    $ogImage = null;
    if (filled($page->seo?->og_image_path)) {
        $ogImage = \Illuminate\Support\Str::startsWith($page->seo->og_image_path, ['http://', 'https://'])
            ? $page->seo->og_image_path
            : asset('storage/' . ltrim($page->seo->og_image_path, '/'));
    }

    $structured = is_array($page->seo?->structured_data) ? array_filter($page->seo->structured_data) : [];

    // content محتوى HTML يحرّره الأدمن. لا يُخرَج عبر {!! !!} إلا بعد تطهير DOM
    // حقيقي يُبقي الوسوم الآمنة فقط ويزيل كل السمات (on*/style/href/src) — بند 4.2.
    $safeContent = filled($page->content) ? \App\Support\HtmlSanitizer::clean($page->content) : '';
@endphp

@section('title', $metaTitle)
@section('meta_description', $metaDesc)

@push('head')
    <meta name="robots" content="{{ $robots }}">
    @if ($canonical)
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDesc }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($ogImage)
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    @if (! empty($structured))
        {{-- HEX flags تمنع كسر السياق بـ </script>؛ UNESCAPED_UNICODE يُبقي العربية مقروءة. --}}
        <script type="application/ld+json">{!! json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
@endpush

@section('content')
    <div class="wrap" style="padding-block:clamp(20px,4vw,34px);max-width:820px">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ $page->title }}</span>
        </nav>

        <article>
            <h1 class="sec-title" style="font-size:clamp(26px,4.4vw,38px)">{{ $page->title }}</h1>

            @if ($safeContent !== '')
                <div class="pdp-desc" style="font-size:16px;margin-top:18px">{!! $safeContent !!}</div>
            @endif
        </article>
    </div>
@endsection
