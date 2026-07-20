@extends('layouts.app')


@section('title', __('account.order.title', ['number' => $order->order_number]) . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

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

        // ── الخط الزمني: رحلة الطلب ──────────────────────────────────────────
        // أوقات حقيقية فقط: خريطة to_status → آخر created_at من سجل التاريخ
        // (append-only). لا نُختلق وقتًا لخطوة لم تُسجَّل — نعرض الوقت حين نملكه.
        $stamp = $order->statusHistories
            ->sortBy('created_at')
            ->groupBy('to_status')
            ->map(fn ($rows) => $rows->last()->created_at);

        $isNegative = in_array($order->status, ['cancelled', 'refused', 'refunded'], true);

        // رُتبة الحالة على المسار السعيد؛ completed يُعامَل كـ delivered (وصل).
        $rank = ['pending' => 0, 'confirmed' => 1, 'processing' => 2, 'shipped' => 3, 'delivered' => 4, 'completed' => 4];
        $currentRank = $rank[$order->status] ?? 0;

        // المعالم الخمسة المرئية للعميلة (المسار السعيد).
        $milestones = [
            ['key' => 'pending',    'emoji' => '📥', 'label' => __('account.order.timeline.received')],
            ['key' => 'confirmed',  'emoji' => '✅', 'label' => __('account.order.timeline.confirmed')],
            ['key' => 'processing', 'emoji' => '📦', 'label' => __('account.order.timeline.processing')],
            ['key' => 'shipped',    'emoji' => '🚚', 'label' => __('account.order.timeline.shipped')],
            ['key' => 'delivered',  'emoji' => '🎉', 'label' => __('account.order.timeline.delivered')],
        ];

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

            @include('partials.account-header', ['sub' => __('account.order.idbar_sub')])

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

            {{-- رحلة الطلب: قلب الصفحة — أين وصل طلبكِ الآن وما الخطوة التالية.
                 أوقاتٌ حقيقية من سجل التاريخ فقط؛ الخطوات القادمة بلا وقت. --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">🗺️</span>{{ __('account.order.timeline.title') }}</h2>

                <ol class="acc-tl">
                    @if ($isNegative)
                        {{-- مسار متوقّف: استلام ثم حالة نهائية سلبية، بلا خطوات لاحقة وهمية --}}
                        <li class="done">
                            <span class="nd" aria-hidden="true">✓</span>
                            <div class="tl-lbl">{{ __('account.order.timeline.received') }}<span class="sr-only"> — {{ __('account.order.timeline.state_done') }}</span></div>
                            @if ($order->created_at)
                                <div class="tl-time">{{ $order->created_at->translatedFormat('Y/m/d — H:i') }}</div>
                            @endif
                        </li>
                        <li class="bad active" aria-current="step">
                            <span class="nd" aria-hidden="true">⚠️</span>
                            <div class="tl-lbl">{{ __('payment.status.' . $order->status) }}<span class="sr-only"> — {{ __('account.order.timeline.state_active') }}</span></div>
                            @if ($stamp[$order->status] ?? null)
                                <div class="tl-time">{{ $stamp[$order->status]->translatedFormat('Y/m/d — H:i') }}</div>
                            @endif
                        </li>
                    @else
                        @foreach ($milestones as $i => $m)
                            @php
                                // عند التسليم/الاكتمال يصير المعلم الأخير «مكتملًا» (✓ هادئ)
                                // لا «نشطًا» ينبض، فالطلب المنتهي لا يبدو جاريًا.
                                $positiveTerminal = in_array($order->status, ['delivered', 'completed'], true);
                                $state = $i < $currentRank
                                    ? 'done'
                                    : ($i === $currentRank ? ($positiveTerminal ? 'done' : 'active') : 'upcoming');
                                // وقت حقيقي فقط: الاستلام من created_at، وبقية المعالم من سجل
                                // التاريخ (delivered يقبل ختم completed). القادم بلا وقت.
                                $t = $m['key'] === 'pending'
                                    ? $order->created_at
                                    : ($stamp[$m['key']] ?? ($m['key'] === 'delivered' ? ($stamp['completed'] ?? null) : null));
                            @endphp
                            <li class="{{ $state }}" @if ($state === 'active') aria-current="step" @endif>
                                <span class="nd" aria-hidden="true">{{ $state === 'done' ? '✓' : $m['emoji'] }}</span>
                                {{-- الحالة تُنقل باللون والأيقونة بصريًا؛ نضيف نصًّا مخفيًا
                                     بصريًا كي لا تعتمد المعلومة على اللون وحده (WCAG 1.4.1). --}}
                                <div class="tl-lbl">{{ $m['label'] }}<span class="sr-only"> — {{ __('account.order.timeline.state_' . $state) }}</span></div>
                                @if ($state !== 'upcoming' && $t)
                                    <div class="tl-time">{{ $t->translatedFormat('Y/m/d — H:i') }}</div>
                                @endif
                            </li>
                        @endforeach
                    @endif
                </ol>
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
