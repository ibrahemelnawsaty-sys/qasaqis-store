{{--
    رسائل تحقّق النماذج بالعربية داخل الصفحة (M11) — على كل الأجهزة.

    فقاعة المتصفح الأصلية («Please fill out this field») تظهر بالإنجليزية على
    الحاسوب و**لا تظهر إطلاقًا على الجوال**. لذلك نكبتها (preventDefault على حدث
    invalid) ونعرض بدلًا منها رسالة عربية واضحة **كعنصر مرئي داخل الصفحة** أسفل
    الحقل مباشرةً، ثم ننتقل إلى أول حقل ناقص. تُمسح عند الكتابة.

    النصوص عبر سمات data من الترجمة (بند 6.4، نمط data-wa-order-intro)، وvanilla
    خفيف (بند 5.2). يُضمّ مرة واحدة في التخطيط فيغطّي كل النماذج.
    ملاحظة: invalid لا يصعد (does not bubble)، فنلتقطه في مرحلة الالتقاط.
--}}
<span id="native-validation-messages" hidden
    data-required="{{ __('forms.native.required') }}"
    data-email="{{ __('forms.native.email') }}"
    data-too-short="{{ __('forms.native.too_short') }}"
    data-pattern="{{ __('forms.native.pattern') }}"
    data-invalid="{{ __('forms.native.invalid') }}"></span>

<style>
    .field-err {
        display: flex; align-items: flex-start; gap: 7px;
        margin-top: 7px; padding: 8px 12px; border-radius: 10px;
        font-size: 14px; line-height: 1.7; font-weight: 700;
        color: #b21f38; background: #fdeaee;
        border: 1.5px solid #f3b6c1;
    }
    .field-err::before { content: "⚠️"; font-size: 15px; line-height: 1.5; }
    :root[data-theme="dark"] .field-err { color: #ffb3c0; background: #3a1a22; border-color: #5c2733; }
    @media (prefers-color-scheme: dark) {
        :root:not([data-theme="light"]) .field-err { color: #ffb3c0; background: #3a1a22; border-color: #5c2733; }
    }
    .field-invalid { border-color: #e74c6a !important; box-shadow: 0 0 0 3px rgba(231,76,106,.15) !important; }
</style>

@push('scripts')
    <script>
        (function () {
            var box = document.getElementById('native-validation-messages');
            if (!box) return;
            var M = box.dataset;

            function messageFor(el) {
                var v = el.validity;
                if (v.valueMissing) return M.required;
                if (v.typeMismatch) return el.type === 'email' ? M.email : M.invalid;
                if (v.tooShort) return M.tooShort.replace(':min', el.minLength);
                if (v.patternMismatch) return M.pattern;
                if (v.badInput || v.rangeOverflow || v.rangeUnderflow || v.stepMismatch) return M.invalid;
                return M.invalid;
            }

            // رسالة الحقل: عنصر يلي الحقل مباشرةً (يُنشأ مرة ويُعاد استعماله).
            function errorFor(el) {
                var next = el.nextElementSibling;
                if (next && next.classList && next.classList.contains('field-err')) return next;
                var p = document.createElement('p');
                p.className = 'field-err';
                p.setAttribute('role', 'alert');
                el.insertAdjacentElement('afterend', p);
                return p;
            }

            function clearFor(el) {
                if (!el || !el.classList || !el.classList.contains('field-invalid')) return;
                el.classList.remove('field-invalid');
                el.removeAttribute('aria-invalid');
                var next = el.nextElementSibling;
                if (next && next.classList && next.classList.contains('field-err')) next.remove();
            }

            var pending = false;

            // مرحلة الالتقاط: invalid لا يصعد. نكبت فقاعة المتصفح ونعرض رسالتنا.
            document.addEventListener('invalid', function (e) {
                var el = e.target;
                if (!el || typeof el.setCustomValidity !== 'function') return;
                e.preventDefault();

                errorFor(el).textContent = messageFor(el);
                el.classList.add('field-invalid');
                el.setAttribute('aria-invalid', 'true');

                // انتقل إلى أول حقل ناقص بعد أن تُطلَق كل أحداث invalid لهذا الإرسال.
                if (!pending) {
                    pending = true;
                    requestAnimationFrame(function () {
                        pending = false;
                        var first = document.querySelector('.field-invalid');
                        if (!first) return;
                        try { first.focus({ preventScroll: true }); } catch (_) {}
                        first.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    });
                }
            }, true);

            // مسح رسالة الحقل عند تصحيحه.
            function onFix(e) { clearFor(e.target); }
            document.addEventListener('input', onFix, true);
            document.addEventListener('change', onFix, true);
        })();
    </script>
@endpush
