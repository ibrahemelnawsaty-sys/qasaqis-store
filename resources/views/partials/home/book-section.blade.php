{{-- قسم كتب في الرئيسية (كاروسيل). المتغيّرات: $section (HomepageSection) · $books (Collection<Book>).
     نفس ماركب «مختارات» السابق، لكن العنوان/التمهيد/الوصف/الزرّ/النقش من القسم. --}}
@php
    $pattern = filled($section->background_pattern)
        ? (\App\Enums\BackgroundPattern::fromValue($section->background_pattern)->cssClass() ?? '')
        : '';
@endphp
<x-section-band :pattern="$pattern">
    <section class="sec" aria-labelledby="sec-{{ $section->id }}">
        <div class="wrap">
            <div class="sec-top">
                @if (filled($section->eyebrow))
                    <span class="sec-eyebrow">{{ $section->eyebrow }}</span>
                @endif
                <h2 class="sec-title" id="sec-{{ $section->id }}">{{ $section->title }}</h2>
                @if (filled($section->subtitle))
                    <p class="sec-desc">{{ $section->subtitle }}</p>
                @endif
            </div>
            <div class="shelf">
                @foreach ($books as $book)
                    <x-book-card :book="$book" />
                @endforeach
            </div>
            @if (filled($section->cta_url))
                <div style="text-align:center;margin-top:32px">
                    <a class="btn btn-ghost" href="{{ $section->cta_url }}">{{ $section->cta_label ?: __('home.view_all') }}</a>
                </div>
            @endif
        </div>
    </section>
</x-section-band>
