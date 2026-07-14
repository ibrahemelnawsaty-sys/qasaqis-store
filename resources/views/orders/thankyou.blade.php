@extends('layouts.app')

@section('title', __('checkout.thankyou.badge') . ' — ' . __('common.brand'))

@section('content')
    @include('partials.checkout-styles')

    @php
        $money = fn ($v) => number_format((float) $v, 0);

        // شارات الحالة.
        $statusClass = match ($order->status) {
            'confirmed', 'processing', 'shipped', 'delivered', 'completed' => 'paid',
            'cancelled', 'refused', 'refunded' => 'bad',
            default => 'wait',
        };
        $payClass = match ($order->payment_status) {
            'paid' => 'paid',
            'failed', 'refunded' => 'bad',
            default => 'wait',
        };

        // رقم واتساب المتجر (يُستخرج مثل مكوّن wa-button).
        $waNumber = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));
        $waText = __('checkout.thankyou.wa_message', ['number' => $order->order_number]);
        $waHref = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode($waText);

        $needsProof = $order->payment_status === 'pending_review';
        $isCod = $order->payment_method === 'cod';
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:760px">

            @if (session('warning'))
                <div class="co-alert notice" role="alert"><span class="ai" aria-hidden="true">ℹ️</span>{{ session('warning') }}</div>
            @endif
            @if (session('status'))
                <div class="co-alert ok" role="status"><span class="ai" aria-hidden="true">✅</span>{{ session('status') }}</div>
            @endif

            {{-- بطاقة الشكر --}}
            <div class="co-hero">
                <div class="em" aria-hidden="true">🎉</div>
                <h1>{{ __('payment.thankyou.title') }}</h1>
                <div class="onum">
                    <span aria-hidden="true">🧾</span>
                    <span>{{ __('checkout.thankyou.order_number') }}: {{ $order->order_number }}</span>
                </div>

                <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;margin-top:16px">
                    <span class="co-badge {{ $statusClass }}">{{ __('checkout.thankyou.order_status') }}: {{ __('payment.status.' . $order->status) }}</span>
                    <span class="co-badge {{ $payClass }}">{{ __('checkout.thankyou.payment_status') }}: {{ __('payment.payment_status.' . $order->payment_status) }}</span>
                </div>
            </div>

            {{-- الخطوة التالية --}}
            <div class="co-card" style="margin-top:16px">
                <h2><span class="n" aria-hidden="true">👉</span>{{ __('checkout.thankyou.next_title') }}</h2>

                @if ($needsProof)
                    <p class="co-lead">{{ __('payment.thankyou.manual_note') }}</p>
                @elseif ($isCod)
                    <p class="co-lead">{{ __('payment.thankyou.cod_note') }}</p>
                @else
                    <p class="co-lead">{{ __('checkout.thankyou.generic_note') }}</p>
                @endif

                <div class="co-actions">
                    @if ($needsProof)
                        <a class="btn btn-primary" href="{{ \Illuminate\Support\Facades\URL::signedRoute('orders.payment', ['order' => $order->id]) }}">📤 {{ __('checkout.thankyou.upload_proof') }}</a>
                    @endif
                    <a class="btn btn-wa" href="{{ $waHref }}" target="_blank" rel="noopener noreferrer">💬 {{ __('checkout.thankyou.next_wa') }}</a>
                    <a class="btn btn-ghost" href="{{ route('books.index') }}">🛍️ {{ __('checkout.thankyou.continue') }}</a>
                </div>
            </div>

            {{-- كتب الطلب (من لقطة order_items — بلا N+1) --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">📚</span>{{ __('checkout.thankyou.items_title') }}</h2>
                <div class="co-items">
                    @foreach ($order->items as $item)
                        <div class="co-item">
                            <span class="co-thumb ph"><span class="co-thumb-i" aria-hidden="true">📖</span></span>
                            <div class="co-item-main">
                                <span class="co-item-title">{{ $item->book_title }}</span>
                                <div class="co-item-meta">{{ $item->quantity }} × {{ $money($item->unit_price) }} {{ __('common.currency') }}</div>
                            </div>
                            <div class="co-item-price">{{ $money($item->line_total) }} {{ __('common.currency') }}</div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:16px">
                    <div class="co-line">
                        <span>{{ __('checkout.summary.subtotal') }}</span>
                        <span class="v">{{ $money($order->subtotal) }} {{ __('common.currency') }}</span>
                    </div>
                    @if ((float) $order->discount_total > 0)
                        <div class="co-line discount">
                            <span>{{ __('checkout.summary.discount') }}@if ($order->coupon_code) <span class="co-mono">({{ $order->coupon_code }})</span>@endif</span>
                            <span class="v">−{{ $money($order->discount_total) }} {{ __('common.currency') }}</span>
                        </div>
                    @endif
                    <div class="co-line">
                        <span>{{ __('checkout.summary.shipping') }}</span>
                        <span class="v">{{ $money($order->shipping_total) }} {{ __('common.currency') }}</span>
                    </div>
                    <div class="co-line total">
                        <span>{{ __('checkout.summary.total') }}</span>
                        <span class="v">{{ $money($order->grand_total) }} {{ __('common.currency') }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
