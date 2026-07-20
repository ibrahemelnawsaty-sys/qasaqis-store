{{-- معاينة معزولة للرسالة داخل iframe: srcdoc يُهرَّب عبر {{ }} فيصير آمنًا كسمة،
     والعزل يمنع أنماط الرسالة من التسرّب إلى لوحة الأدمن أو العكس. --}}
<iframe
    srcdoc="{{ $html }}"
    style="width:100%;height:70vh;border:0;border-radius:12px;background:#F5F1EA;"
    title="معاينة الرسالة"
></iframe>
