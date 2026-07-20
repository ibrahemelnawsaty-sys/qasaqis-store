{{--
    شريط هوية مصغّر يُعاد على الصفحات الفرعية للحساب (طلباتي، الطلب، بياناتي) كي
    يبقى إحساس «هذا حسابي أنا» متّصلًا خارج اللوحة — هدف المالك الأهم (M12).
    يتطلّب account-styles مضمومًا في الصفحة (فئة acc-idbar). النصوص من lang (6.4).

    الوسيط الاختياري $sub: نصّ سياق الصفحة أسفل التحية (مثل «كل طلباتك في مكان واحد»).
--}}
@php
    $accHdrCustomer = auth('customer')->user();
    $accHdrInitial = $accHdrCustomer
        ? mb_strtoupper(mb_substr(trim((string) $accHdrCustomer->name) ?: '؟', 0, 1))
        : '؟';
@endphp

@if ($accHdrCustomer)
    <div class="acc-idbar">
        <span class="m" aria-hidden="true">{{ $accHdrInitial }}</span>
        <div class="t">
            <b>{{ __('account.header.greeting', ['name' => $accHdrCustomer->name]) }}</b>
            <span>{{ $sub ?? __('account.header.default_sub') }}</span>
        </div>
    </div>
@endif
