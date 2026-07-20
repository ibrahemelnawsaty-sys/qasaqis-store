@extends('layouts.app')


@section('title', __('account.orders.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    @include('partials.submit-guard', [
        'formId' => 'attachForm',
        'buttonId' => 'attachSubmit',
        'busyLabel' => __('account.orders.attach.submitting'),
    ])

    @php
        $money = fn ($v) => number_format((float) $v, 0);

        // نبرة لونية للحافة + إيموجي دلالي — مطابقة للوحة وصفحة الطلب كي لا يختلف
        // معنى اللون بين الشاشات.
        $toneFor = fn (string $status): string => match ($status) {
            'delivered', 'completed' => 'ok',
            'cancelled', 'refused', 'refunded' => 'bad',
            default => 'wait',
        };
        $edgeFor = fn (string $tone): string => match ($tone) {
            'ok' => 'var(--teal)', 'bad' => '#e23c3c', default => 'var(--gold)',
        };
        $emojiFor = fn (string $status): string => match ($status) {
            'confirmed' => '✅', 'processing' => '📦', 'shipped' => '🚚',
            'delivered', 'completed' => '🎉',
            'cancelled', 'refused', 'refunded' => '⚠️',
            default => '🧾',
        };
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:760px">

            <p style="margin-bottom:10px">
                <a href="{{ route('customer.dashboard') }}" style="font-size:13.5px;color:var(--ink-soft);text-decoration:none">
                    <span aria-hidden="true">←</span> {{ __('account.nav.dashboard') }}
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
                <h1>{{ __('account.orders.heading') }}</h1>
                <p>{{ __('account.orders.lead') }}</p>
            </div>

            @if ($orders->isNotEmpty())
                <div class="acc-orders">
                    @foreach ($orders as $order)
                        @php
                            // يظهر عدد الكتب فقط حين يحمّله المتحكّم بـ withCount('items')
                            // — لا نعرض صفرًا مخترعًا حين يغيب العمود (بند 1.1).
                            $itemsCount = isset($order->items_count) ? (int) $order->items_count : null;
                            $tone = $toneFor($order->status);
                        @endphp
                        {{-- الصف بأكمله رابط: مساحة لمس واسعة تناسب الهاتف (بند 6.3).
                             الحافة اللونية تُشتَقّ من نبرة الحالة عبر متغيّر --edge. --}}
                        <a class="acc-order" href="{{ route('customer.orders.show', ['order' => $order->id]) }}"
                            style="--edge:{{ $edgeFor($tone) }}"
                            aria-label="{{ __('account.orders.view_aria', ['number' => $order->order_number]) }}">
                            <span class="oc" aria-hidden="true">📖@if ($itemsCount !== null && $itemsCount > 1)<span class="cnt">{{ $itemsCount }}</span>@endif</span>
                            <div class="om">
                                <div class="ost {{ $tone }}"><span aria-hidden="true">{{ $emojiFor($order->status) }}</span>{{ __('payment.status.' . $order->status) }}</div>
                                <div class="omt">
                                    <span class="co-mono">{{ $order->order_number }}</span>
                                    · {{ $order->created_at?->translatedFormat('Y/m/d') }}
                                    @if ($itemsCount !== null)
                                        · {{ trans_choice('checkout.summary.items_count', $itemsCount, ['count' => $itemsCount]) }}
                                    @endif
                                </div>
                            </div>
                            <div class="op">{{ $money($order->grand_total) }} {{ __('common.currency') }}</div>
                        </a>
                    @endforeach

                    <div class="pagination-wrap">
                        {{ $orders->onEachSide(1)->links() }}
                    </div>
                </div>
            @else
                <div class="co-card">
                    <div class="empty-state">
                        <div class="em" aria-hidden="true">📦</div>
                        <h2 class="sec-title" style="font-size:22px">{{ __('account.orders.empty_title') }}</h2>
                        <p class="sec-desc">{{ __('account.orders.empty_desc') }}</p>
                        <div class="co-actions" style="justify-content:center;margin-top:18px">
                            <a class="btn btn-primary" href="{{ route('books.index') }}">🛍️ {{ __('account.orders.empty_cta') }}</a>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ربط طلب سابق قُدّم كضيفة: يتطلّب رقم الطلب + الجوال المسجّل فيه معًا.
                 الجوال وحده لا يربط شيئًا، وطلب واحد في كل عملية. --}}
            <div class="co-card" id="attach">
                <h2><span class="n" aria-hidden="true">➕</span>{{ __('account.orders.attach.title') }}</h2>
                <p class="co-lead">{{ __('account.orders.attach.lead') }}</p>

                <form id="attachForm" method="POST" action="{{ route('customer.orders.attach') }}" style="margin-top:12px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="at-order-number">{{ __('account.orders.attach.order_number') }}</label>
                        <input id="at-order-number" type="text" name="order_number" value="{{ old('order_number') }}"
                            maxlength="20" class="co-input @error('order_number') err @enderror"
                            placeholder="{{ __('account.orders.attach.order_number_ph') }}"
                            dir="ltr" autocomplete="off" required
                            @error('order_number') aria-invalid="true" aria-describedby="at-order-number-err" @enderror>
                        @error('order_number')
                            <p class="co-err" id="at-order-number-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="at-phone">{{ __('account.orders.attach.phone') }}</label>
                        <input id="at-phone" type="tel" name="phone" value="{{ old('phone') }}"
                            maxlength="20" class="co-input @error('phone') err @enderror"
                            placeholder="{{ __('account.orders.attach.phone_ph') }}"
                            inputmode="tel" dir="ltr" autocomplete="tel" required
                            @error('phone') aria-invalid="true" aria-describedby="at-phone-err" @enderror>
                        @error('phone')
                            <p class="co-err" id="at-phone-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-actions">
                        <button id="attachSubmit" type="submit" class="btn btn-primary">{{ __('account.orders.attach.submit') }}</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
