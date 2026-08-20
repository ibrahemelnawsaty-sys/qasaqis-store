{{-- تسريع متقدّم — طبقتان تقدّميّتان (المتصفّحات غير الداعمة تتجاهلهما بأمان):

     (١) قواعد التخمين (Speculation Rules): يجلب المتصفّح صفحة الوجهة مسبقًا عند
         التحويم/بدء الضغط على رابط داخليّ (eagerness=moderate) فيصير التنقّل شبه
         فوريّ — مفيد جدًّا مع بطء TTFB (الأصل بمومباي). نستثني السلة/الدفع/الحساب/
         الدخول/الأدمن/الوسائط كي لا نجلب صفحات ذات حالة أو خاصّة بالمستخدم.
         نستعمل prefetch (جلب HTML فقط) لا prerender (تشغيل JS) تحفّظًا. --}}
@verbatim
<script type="speculationrules">
{
  "prefetch": [{
    "source": "document",
    "where": { "and": [
      { "href_matches": "/*" },
      { "not": { "href_matches": ["/cart*", "/checkout*", "/account*", "/login*", "/logout*", "/register*", "/admin*", "/media/*", "/api/*"] } },
      { "not": { "selector_matches": ".no-prefetch, [rel~=\"nofollow\"], [target=\"_blank\"]" } }
    ]},
    "eagerness": "moderate"
  }]
}
</script>
@endverbatim

{{-- (٢) عامل خدمة PWA: يخزّن الأصول الثابتة المبصومة (build/media-cache/fonts/images)
     محليًّا (stale-while-revalidate) فتُخدَم فورًا في الزيارات العائدة بلا شبكة، ويجعل
     الموقع قابلًا للتثبيت. لا يمسّ صفحات HTML ولا /media ولا الطلبات ذات الحالة إطلاقًا
     (شبكة دائمًا) فلا يُخدَم محتوًى قديم/خاطئ. يُسجَّل بعد التحميل كي لا يزاحم الحرِج. --}}
<script>
    if ('serviceWorker' in navigator) {
        addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js').catch(function () {});
        });
    }
</script>
