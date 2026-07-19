{{--
    هيكل صفحة خطأ ودود (M7). يستخدم رموز التصميم القائمة (var(--…)) لا ألوانًا
    عشوائية (بند 6.1)، وكل النصوص تُمرَّر من ملفات الترجمة (بند 6.4).

    المعاملات: icon · heading · body · hint · ctaUrl · ctaLabel
--}}
<div style="max-width:44rem;margin-inline:auto;padding:clamp(2rem,6vw,5rem) 1rem;text-align:center">

    <div aria-hidden="true" style="font-size:clamp(3rem,10vw,4.5rem);line-height:1;margin-bottom:1rem">{{ $icon }}</div>

    <h1 style="font-size:clamp(1.4rem,4vw,2rem);margin:0 0 .75rem;color:var(--ink)">{{ $heading }}</h1>

    <p style="color:var(--ink-soft);margin:0 auto 1rem;max-width:34rem;line-height:1.8">{{ $body }}</p>

    <p style="color:var(--ink-soft);font-size:.95rem;margin:0 auto 2rem;max-width:34rem;line-height:1.8">{{ $hint }}</p>

    <div style="display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center">
        <a class="btn btn-primary" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>
        <a class="btn btn-ghost" href="{{ route('home') }}">{{ __('errors.back_home') }}</a>
    </div>

</div>
