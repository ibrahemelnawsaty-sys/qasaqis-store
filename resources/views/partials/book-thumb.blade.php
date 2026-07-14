{{-- صورة مصغّرة لكتاب داخل السلة/الملخّص. تحترم بند 0.4/11.22: لا نختلق غلافًا؛
     الكتاب بلا غلاف (مثل BOOK10) يظهر بعنصر بديل محايد بألوان الهوية. --}}
@php
    /** @var \App\Models\Book $book */
    $palettes = [
        ['#6E2FB0', '#EC4E96'], ['#EC4E96', '#FF8A2A'], ['#12B3A6', '#4FB0E8'],
        ['#FF8A2A', '#FFC23C'], ['#6E2FB0', '#12B3A6'], ['#4FB0E8', '#12B3A6'],
    ];
    $pair = $palettes[(int) ($book->id ?? 0) % count($palettes)];
    $cover = $book->cover_image ?? null;
    $src = filled($cover)
        ? (\Illuminate\Support\Str::startsWith($cover, ['http://', 'https://'])
            ? $cover
            : asset('storage/' . ltrim($cover, '/')))
        : null;
@endphp
<span class="co-thumb" style="background:linear-gradient(150deg,{{ $pair[0] }},{{ $pair[1] }})">
    @if ($src)
        <img src="{{ $src }}" alt="{{ $book->title }}" loading="lazy" decoding="async" width="48" height="60">
    @else
        <span class="co-thumb-i" aria-hidden="true">📖</span>
    @endif
</span>
