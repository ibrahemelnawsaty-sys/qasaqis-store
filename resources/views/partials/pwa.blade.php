{{-- تسريع متقدّم — طبقتان تقدّميّتان (المتصفّحات غير الداعمة تتجاهلهما بأمان):

     (١) قواعد التخمين (Speculation Rules): يجلب المتصفّح صفحة الوجهة مسبقًا **عند بدء
         الضغط** على رابط داخليّ (eagerness=conservative) فيصير التنقّل شبه فوريّ بلا
         تضخيم للطلبات. كان moderate (تحويم ≥200ms) يُطلق طلب HTML ديناميكيًّا كاملًا لكل
         رابط يمرّ عليه المؤشّر — فيتجاوز الزائرُ حدَّ Hostinger-CDN (~٢٦ طلبًا/دقيقة، يردّ
         429 بجسم فارغ) فينهار الموقع (qasaqis-429-double-cdn). conservative يجلب فقط ما
         يوشك المستخدم أن ينقره (~طلب واحد لكل تنقّل فعليّ = بلا تضخيم). نستثني السلة/الدفع/
         الحساب/الدخول/الأدمن/الوسائط، ونستعمل prefetch (HTML فقط) لا prerender تحفّظًا. --}}
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
    "eagerness": "conservative"
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
