{{-- قائمة المشاركة المشتركة + معالجها المفوَّض: عنصر واحد للصفحة مهما تعدّدت أزرار
     .book-share (كتب أو مقالات). @once بمعرّف صريح كي لا يتكرّر مهما تعدّدت الاستدعاءات.
     على الجوال: مشاركة الهاتف الأصليّة بضغطة؛ على الحاسوب: قائمة صغيرة (نسخ/واتساب/فيسبوك).
     JS أصليّ خفيف بلا مكتبات (بند 5.2). --}}
@once('share-pop-widget')
    @push('scripts')
        <div id="book-share-pop" class="book-share-pop" role="group" aria-hidden="true" aria-label="{{ __('book.share.native') }}">
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
