@extends('layouts.app')


@section('title', __('account.order.title', ['number' => $order->order_number]) . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @php
        $money = fn ($v) => number_format((float) $v, 0);

        // تصنيف شارات الحالة — مطابق لصفحة الشكر كي لا يختلف اللون بين الصفحتين.
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

        // زرّ رفع الإثبات يظهر فقط حين تستدعيه الحالة ويكون الرابط الموقّع مولَّدًا
        // خادميًا — لا يُبنى رابط موقّع من داخل القالب.
        $canUploadProof = $order->payment_status === 'pending_review' && filled($proofUrl ?? null);

        $hasTracking = filled($order->shipping_company) || filled($order->tracking_number);

        // رقم واتساب المتجر — يُستخرج بنفس طريقة مكوّن wa-button وصفحة الشكر.
        $waNumber = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));
        $waHref = 'https://wa.me/' . $waNumber . '?text='
            . rawurlencode(__('account.order.wa_message', ['number' => $order->order_number]));
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:760px">

            <p style="margin-bottom:10px">
                <a href="{{ route('customer.orders.index') }}" style="font-size:13.5px;color:var(--ink-soft);text-decoration:none">
                    <span aria-hidden="true">←</span> {{ __('account.order.back') }}
                </a>
            </p>

            @if (session('status'))
                <div class="co-alert ok" role="status">
                    <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert">
                    <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                </div>
            @endif

            <div class="co-head">
                <h1>{{ __('account.order.heading') }}</h1>
                <p class="co-mono">{{ $order->order_number }}</p>
            </div>

            {{-- حالة الطلب --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">🧾</span>{{ __('account.order.summary_title') }}</h2>

                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
                    <span class="co-badge {{ $statusClass }}">{{ __('account.order.status') }}: {{ __('payment.status.' . $order->status) }}</span>
                    <span class="co-badge {{ $payClass }}">{{ __('account.order.payment_status') }}: {{ __('payment.payment_status.' . $order->payment_status) }}</span>
                </div>

                <div class="co-line" style="padding-top:0">
                    <span>{{ __('account.order.number') }}</span>
                    <span class="v co-mono">{{ $order->order_number }}</span>
                </div>
                <div class="co-line">
                    <span>{{ __('account.order.placed_at') }}</span>
                    <span class="v">{{ $order->created_at?->translatedFormat('Y/m/d H:i') }}</span>
                </div>
                <div class="co-line">
                    <span>{{ __('account.order.payment_method') }}</span>
                    <span class="v">{{ __('payment.methods.' . $order->payment_method) }}</span>
                </div>

                @if ($canUploadProof)
                    <p class="co-hint" style="margin-top:12px">{{ __('account.order.upload_proof_hint') }}</p>
                @endif

                <div class="co-actions">
                    @if ($canUploadProof)
                        <a class="btn btn-primary" href="{{ $proofUrl }}">📤 {{ __('account.order.upload_proof') }}</a>
                    @endif
                    @if (filled($waNumber))
                        <a class="btn btn-wa" href="{{ $waHref }}" target="_blank" rel="noopener noreferrer">💬 {{ __('account.order.wa_follow') }}</a>
                    @endif
                </div>
            </div>

            {{-- كتب الطلب (من لقطة order_items — بلا N+1) --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">📚</span>{{ __('account.order.items_title') }}</h2>

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

            {{-- عنوان الشحن. رقم الجوال لا يُعرض: هو مفتاح ربط الطلب بالحساب،
                 وعرضه لا يفيد صاحبة الطلب ويوسّع الانكشاف بلا مقابل. --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">🚚</span>{{ __('account.order.shipping_title') }}</h2>
                <dl class="co-dl">
                    <dt>{{ __('checkout.form.name') }}</dt>
                    <dd>{{ $order->customer_name }}</dd>

                    <dt>{{ __('checkout.form.address') }}</dt>
                    <dd>{{ $order->address_line }}</dd>

                    @if (filled($order->city))
                        <dt>{{ __('checkout.form.city') }}</dt>
                        <dd>{{ $order->city }}</dd>
                    @endif

                    @if (filled($order->governorate))
                        <dt>{{ __('checkout.form.governorate') }}</dt>
                        <dd>{{ $order->governorate }}</dd>
                    @elseif (filled($order->state_province))
                        <dt>{{ __('checkout.form.state_province') }}</dt>
                        <dd>{{ $order->state_province }}</dd>
                    @endif

                    @if (filled($order->address_notes))
                        <dt>{{ __('checkout.form.address_notes') }}</dt>
                        <dd>{{ $order->address_notes }}</dd>
                    @endif
                </dl>
            </div>

            @if (filled($order->customer_note))
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">📝</span>{{ __('account.order.note_title') }}</h2>
                    <p class="co-lead">{{ $order->customer_note }}</p>
                </div>
            @endif

            @if ($hasTracking)
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">📍</span>{{ __('account.order.tracking_title') }}</h2>
                    <dl class="co-dl">
                        @if (filled($order->shipping_company))
                            <dt>{{ __('account.order.shipping_company') }}</dt>
                            <dd>{{ $order->shipping_company }}</dd>
                        @endif
                        @if (filled($order->tracking_number))
                            <dt>{{ __('account.order.tracking_number') }}</dt>
                            <dd class="co-mono">{{ $order->tracking_number }}</dd>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- فكّ الربط: عمل عكوس بالكامل (تُعيد إضافته برقم الطلب والجوال)، فلا
                 نضع أمامه خطوة تأكيد تعتمد على JS قد لا يصل على شبكة ضعيفة. النص
                 نفسه يشرح العاقبة قبل الضغط. --}}
            <div class="co-card">
                <p class="co-lead">{{ __('account.orders.detach.confirm') }}</p>

                <form method="POST" action="{{ route('customer.orders.detach', ['order' => $order->id]) }}" style="margin-top:12px">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-ghost btn-block">{{ __('account.orders.detach.submit') }}</button>
                </form>
            </div>

        </div>
    </div>
@endsection
