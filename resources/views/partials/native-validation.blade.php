{{--
    استبدال رسائل تحقّق المتصفح الأصلية بالعربية (M11) في كل نماذج الموقع.

    المتصفح يعرض رسائله بلغته (إنجليزية غالبًا: «Please fill out this field»).
    نعترض حدث invalid ونضع رسالة عربية واضحة عبر setCustomValidity، ونمسحها عند
    الكتابة كي يُعاد التحقق طبيعيًا. النصوص تُمرَّر عبر سمات data من الترجمة (بند
    6.4، نمط data-wa-order-intro القائم) فتبقى عربية حرفية في المصدر؛ وvanilla
    خفيف (بند 5.2). يُضمّ مرة واحدة في التخطيط فيغطّي كل النماذج.

    ملاحظة: invalid لا يصعد (does not bubble)، لذا نلتقطه في مرحلة الالتقاط.
--}}
<span id="native-validation-messages" hidden
    data-required="{{ __('forms.native.required') }}"
    data-email="{{ __('forms.native.email') }}"
    data-too-short="{{ __('forms.native.too_short') }}"
    data-pattern="{{ __('forms.native.pattern') }}"
    data-invalid="{{ __('forms.native.invalid') }}"></span>

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
                return '';
            }

            // مرحلة الالتقاط: invalid لا يصعد فلا تكفي مرحلة الفقاعة.
            document.addEventListener('invalid', function (e) {
                var el = e.target;
                if (!el || typeof el.setCustomValidity !== 'function') return;
                el.setCustomValidity(messageFor(el));
            }, true);

            // مسح الرسالة المخصّصة عند الكتابة كي لا تبقى «عالقة» ويُعاد التحقق.
            document.addEventListener('input', function (e) {
                if (e.target && typeof e.target.setCustomValidity === 'function') {
                    e.target.setCustomValidity('');
                }
            }, true);
        })();
    </script>
@endpush
