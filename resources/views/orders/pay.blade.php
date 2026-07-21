@extends('layouts.app')

@section('title', __('payment.pay.title') . ' — ' . __('common.brand'))

{{-- صفحة دفع خاصة برابط موقّع — يجب ألا تُفهرس. --}}
@section('seo_robots', 'noindex, nofollow')

@section('content')
    @include('partials.checkout-styles')

    @php
        $money2 = fn ($v) => number_format((float) $v, 2);
        $itemsCount = (int) $order->items->sum('quantity');
        $backUrl = \Illuminate\Support\Facades\URL::signedRoute('orders.thankyou', ['order' => $order->id]);
    @endphp

    <style>
        .kpay-secure{ display:flex; align-items:center; gap:9px; justify-content:center; font-size:13.5px; font-weight:700;
            color:var(--purple); background:var(--purple-soft); border:1px solid var(--line); border-radius:14px; padding:11px 14px; }
        .kpay-embed{ display:flex; flex-direction:column; align-items:center; gap:14px; padding:26px 16px 6px; text-align:center; }
        .kpay-embed #kashier-iFrame + *,
        .kpay-embed button,
        .kpay-embed input[type="button"],
        .kpay-embed input[type="submit"]{ min-width:240px; }
        .kpay-hint{ font-size:13px; color:var(--ink-soft); line-height:1.7; max-width:420px; margin-inline:auto; }
        .kpay-methods{ text-align:center; font-size:12.5px; color:var(--ink-soft); margin-top:14px; line-height:1.7; }
        .kpay-back{ display:inline-flex; align-items:center; gap:6px; margin-top:18px; color:var(--ink-soft);
            font-size:13.5px; text-decoration:none; }
        .kpay-back:hover{ color:var(--purple); }
        .kpay-noscript{ text-align:center; color:var(--danger,#b91c1c); font-size:13.5px; padding:14px; }
    </style>

    <div class="co">
        <div class="wrap" style="max-width:720px">

            @if (session('warning'))
                <div class="co-alert notice" role="alert"><span class="ai" aria-hidden="true">ℹ️</span>{{ session('warning') }}</div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
            @endif

            <div class="co-head">
                <h1>{{ __('payment.pay.title') }}</h1>
                <p>{{ __('payment.pay.subtitle') }}</p>
            </div>

            {{-- ملخّص الطلب --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">1</span>{{ __('payment.pay.summary') }}</h2>

                <div class="co-line" style="padding-top:0">
                    <span>{{ __('checkout.payment.order_number') }}</span>
                    <span class="v co-mono">{{ $order->order_number }}</span>
                </div>
                <div class="co-line">
                    <span>{{ __('payment.pay.items_count') }}</span>
                    <span class="v">{{ $itemsCount }}</span>
                </div>

                <div class="co-amount-box">
                    <div class="lbl">{{ __('payment.pay.amount_due') }}</div>
                    <div class="val">{{ $money2($order->grand_total) }} <span style="font-size:.5em">{{ __('common.currency') }}</span></div>
                </div>
            </div>

            {{-- الدفع المدمج --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">2</span>{{ __('payment.methods.online_gateway') }}</h2>

                <div class="kpay-secure">
                    <span aria-hidden="true">🔒</span>{{ __('payment.pay.secure') }}
                </div>

                <div class="kpay-embed">
                    {{-- سكربت كاشير: يعرض زرّ الدفع، والضغط عليه يفتح نافذة الدفع المدمجة
                         (iframe) فوق الصفحة — بلا data-type="external" فلا تنتقل العميلة
                         لموقع خارجي. أسماء السمات مطابقة حرفيًّا لتكامل كاشير. --}}
                    <script
                        id="kashier-iFrame"
                        src="{{ $scriptUrl }}"
                        @foreach ($params as $dataKey => $dataVal) data-{{ $dataKey }}="{{ $dataVal }}" @endforeach
                        data-store="{{ $storeName }}"
                        data-description="{{ $order->order_number }}"
                        data-brandColor="#6E2FB0"></script>

                    <p class="kpay-hint">{{ __('payment.pay.hint') }}</p>

                    <noscript>
                        <p class="kpay-noscript">{{ __('payment.pay.no_js') }}</p>
                    </noscript>
                </div>

                <p class="kpay-methods">💳 {{ __('payment.pay.methods') }}</p>
            </div>

            <div style="text-align:center">
                <a class="kpay-back" href="{{ $backUrl }}">
                    <span aria-hidden="true">→</span>{{ __('payment.pay.back') }}
                </a>
            </div>

        </div>
    </div>
@endsection
