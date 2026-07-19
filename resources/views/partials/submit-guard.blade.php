{{--
    حارس الإرسال المزدوج (M7 — رحلة العميل).

    على الشبكات المصرية البطيئة لا يستجيب الزر فورًا، فتضغطه الأم مرة ثانية وثالثة.
    هذا يمنع الضغطة الثانية أصلًا ويُطمئنها أن شيئًا يحدث. الحماية النهائية خادمية
    دائمًا (مفتاح منع التكرار في الدفع، وحارس الإثبات الأول في الرفع) — هذا تحسين
    تجربة لا بديل عنها.

    Vanilla لا Alpine: السكربت المضمّن في @stack('scripts') ينفَّذ قبل حزمة Vite
    المؤجّلة (بند 5.2)، فلا يحتاج انتظار تهيئة إطار.

    المعاملات:
      formId       — مُعرِّف النموذج.
      buttonId     — مُعرِّف زر الإرسال (قد يكون خارج النموذج عبر form=).
      busyLabel    — نص الانتظار (من ملفات الترجمة — بند 6.4).
      busyTimeout  — مهلة تحرير الزر بالمللي ثانية. شبكة مقطوعة أو ضغط «إيقاف»
                     يترك الصفحة قائمة والزر معطّلًا إلى الأبد بلا هذه المهلة.
      reloadOnRestore — عند الاستعادة من ذاكرة المتصفح (زر الرجوع): إعادة تحميل من
                     الخادم بدل تحرير الزر. تلزم حيث تحمل الجلسة حالة استُهلكت
                     (مفتاح محاولة الدفع)، وإلا بدت الصفحة جاهزة وإرسالها يُردّ بصمت.
--}}
@php
    $busyTimeout = $busyTimeout ?? 20000;
    $reloadOnRestore = $reloadOnRestore ?? false;
@endphp

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById(@js($formId));
            var btn = document.getElementById(@js($buttonId));
            if (!form || !btn) return;

            // الحالة كما رسمها الخادم (قد يكون الزر معطّلًا أصلًا) — لا نتجاوزها.
            var lockedByServer = btn.disabled;
            var originalLabel = btn.textContent;
            var timer = null;

            function release() {
                if (timer) { clearTimeout(timer); timer = null; }
                btn.disabled = lockedByServer;
                btn.textContent = originalLabel;
            }

            form.addEventListener('submit', function () {
                // بعد إطلاق submit فعليًا: لو فشل تحقّق المتصفح الأصلي لا يصل هذا
                // السطر، فلا نُعطّل زرًا يحتاجه العميل لإعادة المحاولة.
                btn.disabled = true;
                btn.textContent = @js($busyLabel);
                timer = setTimeout(release, {{ (int) $busyTimeout }});
            });

            window.addEventListener('pageshow', function (e) {
                if (!e.persisted) return;
                @if ($reloadOnRestore)
                    window.location.reload();
                @else
                    release();
                @endif
            });
        })();
    </script>
@endpush
