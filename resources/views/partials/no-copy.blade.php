{{-- منع نسخ نصوص الموقع (رادع من جهة العميل فقط؛ لا يغني عن الحماية الخادمية).
     يُعطَّل التحديد والنسخ والقصّ وقائمة السياق والسحب على محتوى الواجهة، مع استثناء
     حقول الإدخال (بحث/دفع/مراجعات) وأي عنصر يُعلَّم صراحةً بـ .is-selectable
     (مثل رمز كوبون يُراد نسخه) كي لا تنكسر تجربة الاستخدام. لا يمسّ لوحة الأدمن
     لأنها بقالب منفصل. عكسه: حذف هذا السطر وملفّه. --}}
<style>
    body {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
        -webkit-touch-callout: none; /* يمنع قائمة النسخ عند الضغط المطوّل (iOS) */
    }
    /* استثناءات: حقول الإدخال والمحتوى القابل للتحرير وأي عنصر مُعلَّم بالسماح */
    input, textarea, select,
    [contenteditable=""], [contenteditable="true"],
    .is-selectable, .is-selectable * {
        -webkit-user-select: text;
        -moz-user-select: text;
        -ms-user-select: text;
        user-select: text;
        -webkit-touch-callout: default;
    }
    /* منع سحب الصور لحفظها (مكمّل لعلامة الأغلفة المائية وحجب الزواحف) */
    img { -webkit-user-drag: none; user-drag: none; }
</style>
<script>
    (function () {
        // هل الهدف داخل حقل إدخال/محتوى قابل للتحرير/عنصر مسموح؟ (نصعد من العقدة النصّية لأبيها)
        function allowed(t) {
            var el = (t && t.nodeType === 3) ? t.parentElement : t;
            return !!(el && el.closest && el.closest(
                'input, textarea, select, [contenteditable=""], [contenteditable="true"], .is-selectable'
            ));
        }
        ['copy', 'cut', 'contextmenu', 'selectstart', 'dragstart'].forEach(function (ev) {
            document.addEventListener(ev, function (e) {
                if (allowed(e.target)) return; // اسمح داخل الحقول والعناصر المُستثناة
                e.preventDefault();
            }, { capture: true });
        });
    })();
</script>
