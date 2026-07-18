{{--
    قالب الترقيم المعتمد لواجهة المتجر — يتجاوز قالب Laravel الافتراضي
    (Illuminate\Pagination::tailwind) لأن الأخير يستخدم تدرّج Tailwind الرمادي
    وسِمات `dark:` التي لا تعمل هنا (darkMode مضبوط على [data-theme="dark"] فقط،
    فينكسر الوضع الليلي التلقائي عبر prefers-color-scheme).

    البديل: فئات نظام التصميم (.pgn*) المبنية على CSS variables في app.css،
    فتتبع الثيمين تلقائيًا. كل النصوص من lang/ar/pagination.php (الباب 6.4)،
    والاتجاه RTL أصيل بخصائص منطقية وأسهم معكوسة صحيحًا (الباب 6.2).
--}}
@if ($paginator->hasPages())
    <nav class="pgn" role="navigation" aria-label="{{ __('pagination.label') }}">
        @if (! is_null($paginator->firstItem()))
            <p class="pgn-sum">
                {{ __('pagination.showing', [
                    'first' => $paginator->firstItem(),
                    'last' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                ]) }}
            </p>
        @endif

        <ul class="pgn-list">
            {{-- السابق --}}
            <li>
                @if ($paginator->onFirstPage())
                    <span class="pgn-btn is-disabled" aria-hidden="true">
                        <span class="pgn-ico pgn-ico-prev"><x-ui-icon name="chevron" :size="18" /></span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pgn-btn"
                        aria-label="{{ __('pagination.previous') }}">
                        <span class="pgn-ico pgn-ico-prev"><x-ui-icon name="chevron" :size="18" /></span>
                    </a>
                @endif
            </li>

            {{-- أرقام الصفحات والفواصل (…) كما يبنيها الـ paginator --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li><span class="pgn-gap" aria-hidden="true">{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        <li>
                            @if ($page == $paginator->currentPage())
                                <span class="pgn-btn is-current" aria-current="page"
                                    aria-label="{{ __('pagination.current_page', ['page' => $page]) }}">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="pgn-btn"
                                    aria-label="{{ __('pagination.goto_page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach

            {{-- التالي --}}
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pgn-btn"
                        aria-label="{{ __('pagination.next') }}">
                        <span class="pgn-ico pgn-ico-next"><x-ui-icon name="chevron" :size="18" /></span>
                    </a>
                @else
                    <span class="pgn-btn is-disabled" aria-hidden="true">
                        <span class="pgn-ico pgn-ico-next"><x-ui-icon name="chevron" :size="18" /></span>
                    </span>
                @endif
            </li>
        </ul>
    </nav>
@endif
