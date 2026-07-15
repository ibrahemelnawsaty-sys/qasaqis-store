@extends('layouts.app')

@section('title', __('blog.index_title') . ' — ' . __('common.brand'))
@section('meta_description', __('blog.index_meta'))

@push('meta')
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ __('blog.index_title') }} — {{ __('common.brand') }}">
    <meta property="og:description" content="{{ __('blog.index_meta') }}">
@endpush

@section('content')
    @php
        // تدرّجات محايدة على هوية العلامة كعنصر بديل للمقالات بلا غلاف (لا نخترع صورة).
        $blogPalettes = [
            ['#6E2FB0', '#EC4E96'],
            ['#EC4E96', '#FF8A2A'],
            ['#12B3A6', '#4FB0E8'],
            ['#FF8A2A', '#FFC23C'],
            ['#6E2FB0', '#12B3A6'],
            ['#4FB0E8', '#12B3A6'],
        ];
    @endphp

    <div class="wrap" style="padding-block:clamp(20px,4vw,34px)">
        <nav class="breadcrumb" aria-label="breadcrumb">
            <a href="{{ route('home') }}">{{ __('nav.home') }}</a>
            <span aria-hidden="true">/</span>
            <span>{{ __('nav.blog') }}</span>
        </nav>

        <div class="sec-top" style="text-align:start;margin:0 0 22px;max-width:none">
            <span class="sec-eyebrow">{{ __('blog.index_eyebrow') }}</span>
            <h1 class="sec-title" style="margin-top:6px">{{ __('blog.index_heading') }}</h1>
            <p class="sec-desc">{{ __('blog.index_desc') }}</p>
        </div>

        @if ($articles->isNotEmpty())
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(clamp(250px,30%,330px),1fr));gap:clamp(16px,2.4vw,24px)">
                @foreach ($articles as $article)
                    @php
                        $pair = $blogPalettes[(int) $article->id % count($blogPalettes)];
                        $coverSrc = filled($article->cover_image)
                            ? (\Illuminate\Support\Str::startsWith($article->cover_image, ['http://', 'https://'])
                                ? $article->cover_image
                                : asset('storage/' . ltrim($article->cover_image, '/')))
                            : null;
                        $articleUrl = route('blog.show', $article);
                        $linkedBooks = $article->books->count();
                    @endphp

                    <article style="display:flex;flex-direction:column;background:var(--surface);border:1px solid var(--line, rgba(0,0,0,.06));border-radius:18px;overflow:hidden;box-shadow:0 6px 20px rgba(0,0,0,.05)">
                        <a href="{{ $articleUrl }}"
                            style="display:block;position:relative;aspect-ratio:16/10;background:linear-gradient(150deg,{{ $pair[0] }},{{ $pair[1] }})"
                            aria-label="{{ $article->title }}">
                            @if ($coverSrc)
                                <img src="{{ $coverSrc }}" alt="{{ __('blog.cover_alt', ['title' => $article->title]) }}"
                                    loading="lazy" decoding="async" width="360" height="225"
                                    style="width:100%;height:100%;object-fit:cover"
                                    onerror="this.style.display='none'">
                            @else
                                <span aria-hidden="true"
                                    style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:40px">📖</span>
                            @endif
                            @if (filled($article->category))
                                <span style="position:absolute;inset-block-start:12px;inset-inline-start:12px;background:rgba(255,255,255,.92);color:var(--purple);font-weight:800;font-size:12px;padding:4px 10px;border-radius:999px">{{ $article->category }}</span>
                            @endif
                        </a>

                        <div style="display:flex;flex-direction:column;gap:8px;padding:16px;flex:1">
                            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:12.5px;color:var(--ink-soft)">
                                <span>⏱️ {{ trans_choice('blog.read_minutes', (int) $article->reading_minutes, ['count' => (int) $article->reading_minutes]) }}</span>
                                @if ($linkedBooks > 0)
                                    <span>📚 {{ trans_choice('blog.related_books_count', $linkedBooks, ['count' => $linkedBooks]) }}</span>
                                @endif
                            </div>

                            <a href="{{ $articleUrl }}"
                                style="font-weight:800;font-size:17px;line-height:1.5;color:var(--ink);text-decoration:none">{{ $article->title }}</a>

                            @if (filled($article->excerpt))
                                <p style="font-size:13.5px;color:var(--ink-soft);line-height:1.7;margin:0">{{ \Illuminate\Support\Str::limit($article->excerpt, 120) }}</p>
                            @endif

                            <a href="{{ $articleUrl }}"
                                style="margin-top:auto;padding-top:6px;font-weight:800;font-size:13.5px;color:var(--purple);text-decoration:none">{{ __('blog.read_more') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pagination-wrap">
                {{ $articles->onEachSide(1)->links() }}
            </div>
        @else
            <div class="empty-state">
                <div class="em" aria-hidden="true">📝</div>
                <h2 class="sec-title" style="font-size:22px">{{ __('blog.empty_title') }}</h2>
                <p class="sec-desc">{{ __('blog.empty_hint') }}</p>
                <div style="margin-top:18px">
                    <a class="btn btn-ghost" href="{{ route('books.index') }}">{{ __('nav.all_books') }}</a>
                </div>
            </div>
        @endif
    </div>
@endsection
