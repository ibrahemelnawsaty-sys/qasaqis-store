@props(['book'])

@php
    $url = route('books.show', $book);
    $hasPrice = $book->price !== null;
    $inStock = $book->stock_status === 'in_stock';
    $canBuy = $hasPrice && $inStock;

    // Struck-through offer: old_price is the (higher) original, price is what you pay.
    $onSale = $hasPrice && $book->old_price !== null && (float) $book->old_price > (float) $book->price;
    $discount = $onSale
        ? (int) round((((float) $book->old_price - (float) $book->price) / (float) $book->old_price) * 100)
        : null;

    // Age display: prefer the admin label, else build from min/max, else nothing.
    $ageText = filled($book->age_label) ? $book->age_label : null;
    if (! $ageText) {
        if ($book->age_min !== null && $book->age_max !== null) {
            $ageText = __('book.age_range', ['min' => $book->age_min, 'max' => $book->age_max]);
        } elseif ($book->age_min !== null) {
            $ageText = __('book.age_from', ['min' => $book->age_min]);
        } elseif ($book->age_max !== null) {
            $ageText = __('book.age_to', ['max' => $book->age_max]);
        }
    }

    $priceDisplay = $hasPrice
        ? number_format((float) $book->price, 0) . ' ' . __('common.currency')
        : null;

    $cartPayload = [
        'id' => $book->id,
        'title' => $book->title,
        'price' => $priceDisplay,
        'url' => $url,
    ];

    // نصّ المشاركة المعبّأ (يطابق شريط صفحة الكتاب) — يُحمل على الزرّ لتقرأه المشاركة الأصليّة/السوشيال.
    $shareText = __('book.share.text', ['title' => $book->title]);
@endphp

<article class="book">
    <x-book-cover :book="$book" :href="$url">
        @if ($discount)
            <span class="disc">{{ $discount }}%<small>{{ __('common.discount_badge') }}</small></span>
        @endif
    </x-book-cover>

    <div class="book-body">
        @if ($book->category)
            <a class="book-cat" href="{{ route('categories.show', $book->category) }}">{{ $book->category->name }}</a>
        @endif

        <a class="book-title" href="{{ $url }}">{{ $book->title }}</a>

        @if ($ageText)
            <div class="book-age">👧 {{ $ageText }}</div>
        @endif

        <div class="book-foot">
            <div class="price">
                @if ($hasPrice)
                    <span class="now">{{ number_format((float) $book->price, 0) }}</span>
                    <span class="cur">{{ __('common.currency') }}</span>
                    @if ($onSale)
                        <span class="old">{{ number_format((float) $book->old_price, 0) }}</span>
                    @endif
                @else
                    <span class="na">{{ __('common.price_unavailable') }}</span>
                @endif
            </div>

            <div class="book-actions">
                {{-- مشاركة الكتاب: على الجوال تفتح قائمة الهاتف الأصليّة مباشرةً، وعلى الحاسوب
                     قائمة صغيرة (نسخ الرابط/واتساب/فيسبوك). القائمة والسكربت يُصيَّران مرّة
                     واحدة للصفحة عبر @once أسفل المكوّن (بند 5.2: JS أصلي خفيف، لا مكتبات). --}}
                <button type="button" class="book-share"
                    data-share-url="{{ $url }}"
                    data-share-title="{{ $book->title }}"
                    data-share-text="{{ $shareText }}"
                    aria-haspopup="true" aria-expanded="false"
                    aria-label="{{ __('book.share.card_aria', ['title' => $book->title]) }}"
                    title="{{ __('book.share.label') }}">
                    <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
                </button>

                <button type="button" class="add"
                    @if ($canBuy)
                        @click="$store.cart.add({{ \Illuminate\Support\Js::from($cartPayload) }})"
                    @else
                        disabled
                    @endif
                    aria-label="{{ __('common.add_to_cart') }}">+</button>
            </div>
        </div>
    </div>
</article>

{{-- قائمة مشاركة البطاقة المشتركة + سكربتها: عنصر واحد للصفحة مهما تعدّدت البطاقات.
     تظهر فقط حين لا يدعم المتصفّح مشاركة الهاتف الأصليّة (غالبًا الحاسوب). --}}
@once
    @push('scripts')
        <div id="book-share-pop" class="book-share-pop" role="group" aria-hidden="true" aria-label="{{ __('book.share.label') }}">
            <button type="button" class="bsp-item" data-bsp-copy>
                <svg class="bsp-ic-copy" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                <svg class="bsp-ic-done" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><polyline points="20 6 9 17 4 12"/></svg>
                <span data-bsp-copy-label>{{ __('book.share.copy') }}</span>
            </button>
            <a class="bsp-item" data-bsp-wa target="_blank" rel="noopener noreferrer" href="#">
                <x-social-icon name="whatsapp" />
                <span>{{ __('book.share.whatsapp') }}</span>
            </a>
            <a class="bsp-item" data-bsp-fb target="_blank" rel="noopener noreferrer" href="#">
                <x-social-icon name="facebook" />
                <span>{{ __('book.share.facebook') }}</span>
            </a>
        </div>
        <script>
            (function () {
                var pop = document.getElementById('book-share-pop');
                if (! pop) { return; }
                var waLink = pop.querySelector('[data-bsp-wa]');
                var fbLink = pop.querySelector('[data-bsp-fb]');
                var copyBtn = pop.querySelector('[data-bsp-copy]');
                var copyLabel = pop.querySelector('[data-bsp-copy-label]');
                var icCopy = pop.querySelector('.bsp-ic-copy');
                var icDone = pop.querySelector('.bsp-ic-done');
                var idleLabel = copyLabel.textContent;
                var doneLabel = @js(__('book.share.copied'));
                var trigger = null, shareUrl = '', timer = null;

                // على الجوال (يدعم المشاركة الأصليّة) لا تُفتح قائمة DOM إطلاقًا؛ نزيل سمات
                // القائمة من الأزرار كي لا تُعلن للـAT قائمةً لا تظهر (بند 6.3: ARIA دقيقة).
                if (navigator.share) {
                    Array.prototype.forEach.call(document.querySelectorAll('.book-share'), function (b) {
                        b.removeAttribute('aria-haspopup');
                        b.removeAttribute('aria-expanded');
                    });
                }

                function resetCopy() {
                    copyBtn.classList.remove('is-copied');
                    copyLabel.textContent = idleLabel;
                    icCopy.hidden = false;
                    icDone.hidden = true;
                }
                function close() {
                    if (! pop.classList.contains('is-open')) { return; }
                    clearTimeout(timer);
                    // نعيد التركيز للزرّ إن كان داخل القائمة (الإغلاق التلقائي بعد النسخ + Escape)،
                    // دون إزعاجه عند الإغلاق بنقرة خارجيّة بالفأرة.
                    var t = trigger, restore = t && pop.contains(document.activeElement);
                    pop.classList.remove('is-open');
                    pop.setAttribute('aria-hidden', 'true');
                    if (t) { t.setAttribute('aria-expanded', 'false'); }
                    trigger = null;
                    if (restore) { t.focus(); }
                }
                // يُموضِع القائمة الثابتة بجانب الزرّ: أسفله، أو أعلاه إن ضاق المكان، محصورة داخل الشاشة.
                function place(btn) {
                    pop.style.visibility = 'hidden';
                    pop.classList.add('is-open');
                    var r = btn.getBoundingClientRect();
                    var pw = pop.offsetWidth, ph = pop.offsetHeight, m = 8;
                    var top = r.bottom + 6;
                    if (top + ph > window.innerHeight - m) { top = r.top - ph - 6; }
                    if (top < m) { top = m; }
                    var left = r.right - pw; // RTL: نحاذي الحافّة اليمنى للقائمة بيمين الزرّ.
                    if (left < m) { left = m; }
                    if (left + pw > window.innerWidth - m) { left = window.innerWidth - m - pw; }
                    pop.style.top = top + 'px';
                    pop.style.left = left + 'px';
                    pop.style.visibility = '';
                }
                function open(btn) {
                    shareUrl = btn.getAttribute('data-share-url') || '';
                    var text = btn.getAttribute('data-share-text') || '';
                    var enc = encodeURIComponent(shareUrl);
                    waLink.href = 'https://wa.me/?text=' + encodeURIComponent(text + ' ' + shareUrl);
                    fbLink.href = 'https://www.facebook.com/sharer/sharer.php?u=' + enc;
                    resetCopy();
                    trigger = btn;
                    btn.setAttribute('aria-expanded', 'true');
                    pop.setAttribute('aria-hidden', 'false');
                    place(btn);
                    var first = pop.querySelector('.bsp-item');
                    if (first) { first.focus(); }
                }

                function markCopied() {
                    copyBtn.classList.add('is-copied');
                    copyLabel.textContent = doneLabel;
                    icCopy.hidden = true;
                    icDone.hidden = false;
                    clearTimeout(timer);
                    timer = setTimeout(close, 1100);
                }
                // بديل النسخ للسياقات غير الآمنة/متصفّحات قديمة لا توفّر Clipboard API.
                function legacyCopy(text) {
                    try {
                        var ta = document.createElement('textarea');
                        ta.value = text;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'fixed';
                        ta.style.top = '0';
                        ta.style.opacity = '0';
                        document.body.appendChild(ta);
                        ta.select();
                        var ok = document.execCommand('copy');
                        document.body.removeChild(ta);
                        return ok;
                    } catch (e) { return false; }
                }

                document.addEventListener('click', function (e) {
                    var btn = e.target.closest ? e.target.closest('.book-share') : null;
                    if (btn) {
                        e.preventDefault();
                        // على الجوال: مشاركة الهاتف الأصليّة (واتساب/فيسبوك/كل التطبيقات) بضغطة.
                        if (navigator.share) {
                            navigator.share({
                                title: btn.getAttribute('data-share-title') || '',
                                text: btn.getAttribute('data-share-text') || '',
                                url: btn.getAttribute('data-share-url') || ''
                            }).catch(function () {});
                            return;
                        }
                        if (trigger === btn) { close(); return; } // ضغطة ثانية على نفس الزرّ تُغلق.
                        close();
                        open(btn);
                        return;
                    }
                    if (! e.target.closest('#book-share-pop')) { close(); } // نقرة خارج القائمة تُغلقها.
                });

                copyBtn.addEventListener('click', function () {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(shareUrl).then(markCopied).catch(function () {
                            if (legacyCopy(shareUrl)) { markCopied(); }
                        });
                        return;
                    }
                    if (legacyCopy(shareUrl)) { markCopied(); } // بلا Clipboard API: بديل execCommand.
                });
                waLink.addEventListener('click', close);
                fbLink.addEventListener('click', close);

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && trigger) { close(); } // close() يعيد التركيز للزرّ.
                });
                window.addEventListener('scroll', close, true);
                window.addEventListener('resize', close);
            })();
        </script>
    @endpush
@endonce
