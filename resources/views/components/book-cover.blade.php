@props([
    'book',
    'variant' => 'card', // card | pdp
    'href' => null,
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

    $cover = $book->cover_image ?? null;
    $src = null;
    if (filled($cover)) {
        $src = \Illuminate\Support\Str::startsWith($cover, ['http://', 'https://'])
            ? $cover
            : asset('storage/' . ltrim($cover, '/'));
    }

    $coverClass = $variant === 'pdp' ? 'pdp-cover' : 'cover';
    $style = 'background:linear-gradient(150deg,' . $pair[0] . ',' . $pair[1] . ')';
@endphp

@if ($href)
    <a href="{{ $href }}" class="{{ $coverClass }}" style="{{ $style }}">
        {{ $slot }}
        @if ($src)
            <img src="{{ $src }}" alt="{{ $book->title }}" loading="lazy" decoding="async" width="360" height="440" onerror="this.style.display='none'">
        @else
            <span class="ctitle">{{ $book->title }}</span>
        @endif
    </a>
@else
    <div class="{{ $coverClass }}" style="{{ $style }}">
        {{ $slot }}
        @if ($src)
            <img src="{{ $src }}" alt="{{ $book->title }}" loading="lazy" decoding="async" width="360" height="440" onerror="this.style.display='none'">
        @else
            <span class="ctitle">{{ $book->title }}</span>
        @endif
    </div>
@endif
