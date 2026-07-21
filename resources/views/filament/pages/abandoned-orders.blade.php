{{--
    قالب «متابعة الطلبات المتروكة». كل النصوص عربية inline على نمط لوحة الأدمن، وCSS
    خالص يتبع الوضع الداكن. أزرار التواصل روابط wa.me/mailto (بلا خادم)، وتوليد الخصم
    wire:click محروس بـ coupons.manage. رسالة المتابعة تُبنى لكل طلب، وتضمّ كود الخصم
    إن وُلِّد.
--}}
<x-filament-panels::page>
    @php
        $percent = \App\Filament\Pages\AbandonedOrders::DISCOUNT_PERCENT;
        $days = \App\Filament\Pages\AbandonedOrders::COUPON_DAYS;
        $payLabels = \App\Filament\Resources\OrderResource::PAYMENT_METHOD_LABELS;
        $payStatus = \App\Filament\Resources\OrderResource::PAYMENT_STATUS_LABELS;
    @endphp

    <style>
        .abn { --card:#fff; --bg:#F7F4FB; --ink:#2E2440; --soft:#6E6280; --faint:#A99FB6; --line:rgba(46,36,64,.12); --accent:#6E2FB0; --wa:#25D366; }
        .dark .abn, .fi-theme-dark .abn { --card:#1F1A2E; --bg:#16121F; --ink:#F1E9FA; --soft:#B9A9CE; --faint:#7d6f90; --line:rgba(255,255,255,.12); --accent:#C79BF0; }
        .abn { color:var(--ink); display:flex; flex-direction:column; gap:14px; }
        .abn-intro { font-size:13.5px; color:var(--soft); }
        .abn-card { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:16px 18px; display:flex; flex-direction:column; gap:10px; }
        .abn-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .abn-num { font-weight:900; font-size:15px; font-variant-numeric:tabular-nums; }
        .abn-age { font-size:12px; color:var(--faint); }
        .abn-badge { font-size:11.5px; font-weight:800; padding:2px 10px; border-radius:999px; background:color-mix(in srgb,#F59E0B 16%,transparent); color:#B45309; }
        .fi-theme-dark .abn-badge, .dark .abn-badge { color:#FCD34D; }
        .abn-who { display:flex; flex-wrap:wrap; gap:8px 18px; font-size:13.5px; }
        .abn-who b { font-weight:800; }
        .abn-who span { color:var(--soft); }
        .abn-books { font-size:13px; color:var(--ink); background:var(--bg); border-radius:10px; padding:8px 12px; }
        .abn-money { font-size:14px; font-weight:800; }
        .abn-actions { display:flex; flex-wrap:wrap; gap:8px; margin-top:2px; }
        .abn-btn { display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:800; text-decoration:none; border-radius:999px; padding:8px 15px; border:1px solid var(--line); background:var(--card); color:var(--ink); cursor:pointer; }
        .abn-btn:hover { border-color:var(--accent); color:var(--accent); }
        .abn-btn.wa { background:var(--wa); border-color:var(--wa); color:#fff; }
        .abn-btn.wa:hover { filter:brightness(1.05); color:#fff; }
        .abn-btn.gift { background:var(--accent); border-color:var(--accent); color:#fff; }
        .abn-btn.gift:hover { filter:brightness(1.06); color:#fff; }
        .abn-btn.off { opacity:.5; cursor:default; pointer-events:none; }
        .abn-code { font-size:13px; font-weight:800; background:color-mix(in srgb,var(--accent) 12%,transparent); border:1px dashed var(--accent); color:var(--accent); border-radius:10px; padding:7px 12px; display:inline-flex; align-items:center; gap:8px; align-self:flex-start; }
        .abn-code code { font-family:ui-monospace,monospace; font-size:14px; letter-spacing:1px; }
        .abn-empty { background:var(--card); border:1px solid var(--line); border-radius:16px; padding:40px 20px; text-align:center; color:var(--soft); }
        .abn-empty .em { font-size:2.6rem; }
        .abn-pager { display:flex; align-items:center; gap:12px; justify-content:center; padding-top:4px; }
        .abn-pg { background:var(--card); border:1px solid var(--line); border-radius:999px; padding:7px 16px; font-size:13px; font-weight:700; color:var(--ink); cursor:pointer; }
        .abn-pg.is-off { opacity:.45; cursor:default; }
        .abn-pg-info { font-size:12.5px; color:var(--soft); }
    </style>

    <div class="abn">
        <p class="abn-intro">عملاء بدؤوا طلبًا ولم يُكملوا الدفع/التأكيد. تواصل معهم لاستعادة البيع — واعرض كود خصم إن لزم. (الأقدم أولًا.)</p>

        @forelse ($orders as $order)
            @php
                $code = $this->coupons[$order->id] ?? null;
                $total = number_format((float) $order->grand_total, 0);
                $msg = 'السلام عليكم '.$order->customer_name.' 🌸'."\n"
                    .'لاحظنا أنك بدأتِ طلبًا (رقم '.$order->order_number.') بمبلغ '.$total.' ج.م ولم يكتمل بعد.'."\n"
                    .'يسعدنا مساعدتك في إتمامه!';
                if ($code) {
                    $msg .= "\n".'🎁 وهديّة لك: كود خصم '.$code.' — خصم '.$percent.'% صالح '.$days.' أيام.';
                }
                $waDigits = \App\Support\Phone\PhoneNormalizer::normalize($order->customer_phone);
                $waHref = $waDigits ? 'https://wa.me/20'.$waDigits.'?text='.rawurlencode($msg) : null;
                $mailHref = filled($order->customer_email)
                    ? 'mailto:'.$order->customer_email.'?subject='.rawurlencode('بخصوص طلبك رقم '.$order->order_number).'&body='.rawurlencode($msg)
                    : null;
            @endphp
            <div class="abn-card">
                <div class="abn-top">
                    <span class="abn-num">{{ $order->order_number }}</span>
                    <span class="abn-age">منذ {{ $order->created_at?->diffForHumans(null, true) }}</span>
                    <span class="abn-badge">{{ $payStatus[$order->payment_status] ?? $order->payment_status }}</span>
                </div>

                <div class="abn-who">
                    <span>العميل: <b>{{ $order->customer_name }}</b></span>
                    <span>الجوال: <b dir="ltr">{{ $order->customer_phone }}</b></span>
                    @if (filled($order->customer_email))<span>الإيميل: <b dir="ltr">{{ $order->customer_email }}</b></span>@endif
                    @if (filled($order->governorate))<span>المحافظة: <b>{{ $order->governorate }}</b></span>@endif
                </div>

                <div class="abn-books">
                    📚
                    @foreach ($order->items as $item)
                        {{ $item->book_title }}@if ($item->quantity > 1) (×{{ $item->quantity }})@endif@if (! $loop->last) ·@endif
                    @endforeach
                </div>

                <div class="abn-who">
                    <span>الإجمالي: <b class="abn-money">{{ $total }} ج.م</b></span>
                    <span>طريقة الدفع: <b>{{ $payLabels[$order->payment_method] ?? $order->payment_method }}</b></span>
                </div>

                @if ($code)
                    <span class="abn-code">🎁 كود الخصم: <code>{{ $code }}</code> — أرسله للعميل</span>
                @endif

                <div class="abn-actions">
                    @if ($waHref)
                        <a class="abn-btn wa" href="{{ $waHref }}" target="_blank" rel="noopener">💬 واتساب</a>
                    @else
                        <span class="abn-btn off">💬 واتساب (رقم غير صالح)</span>
                    @endif

                    @if ($mailHref)
                        <a class="abn-btn" href="{{ $mailHref }}">✉️ إيميل</a>
                    @endif

                    @if ($canCoupon && ! $code)
                        <button type="button" class="abn-btn gift" wire:click="generateCoupon({{ $order->id }})" wire:loading.attr="disabled" wire:target="generateCoupon({{ $order->id }})">
                            🎁 توليد كود خصم
                        </button>
                    @endif

                    <a class="abn-btn" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $order->getKey()]) }}">عرض الطلب ←</a>
                </div>
            </div>
        @empty
            <div class="abn-empty">
                <div class="em" aria-hidden="true">🎉</div>
                <p style="margin-top:8px;font-weight:800">لا طلبات متروكة الآن — كل من بدأ طلبًا أكمله أو يُتابَع.</p>
            </div>
        @endforelse

        @if ($orders->hasPages())
            <div class="abn-pager">
                @if ($orders->onFirstPage())
                    <span class="abn-pg is-off">السابق</span>
                @else
                    <button type="button" class="abn-pg" wire:click="previousPage">السابق</button>
                @endif
                <span class="abn-pg-info">صفحة {{ $orders->currentPage() }} من {{ $orders->lastPage() }} · {{ number_format($orders->total()) }} طلب</span>
                @if ($orders->hasMorePages())
                    <button type="button" class="abn-pg" wire:click="nextPage">التالي</button>
                @else
                    <span class="abn-pg is-off">التالي</span>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>
