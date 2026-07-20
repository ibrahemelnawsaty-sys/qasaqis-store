@extends('layouts.app')


@section('title', __('account.dashboard.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @php
        $customer = auth('customer')->user();
        $money = fn ($v) => number_format((float) $v, 0);

        // تصنيف شارة الحالة — مطابق لصفحة الشكر كي لا يختلف اللون بين الصفحتين.
        $statusClass = $lastOrder ? match ($lastOrder->status) {
            'confirmed', 'processing', 'shipped', 'delivered', 'completed' => 'paid',
            'cancelled', 'refused', 'refunded' => 'bad',
            default => 'wait',
        } : 'wait';
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:760px">

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

            {{-- تذكير غير حاجب: بريدك غير مؤكّد بعد (M9). زر يعيد لصفحة إدخال الكود. --}}
            @if ($customer->email_verified_at === null)
                <div class="co-alert notice" role="status" style="align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <span><span class="ai" aria-hidden="true">📧</span>{{ __('account.verify.unverified_banner') }}</span>
                    <a class="btn btn-ghost" href="{{ route('customer.verify.show') }}" style="font-size:13.5px">{{ __('account.verify.title') }}</a>
                </div>
            @endif

            <div class="co-head">
                <h1>{{ __('account.dashboard.greeting', ['name' => $customer->name]) }}</h1>
                <p>{{ __('account.dashboard.lead') }}</p>
            </div>

            {{-- آخر طلب. العدّاد محسوب عند العرض من جدول الطلبات — لا عمود مخزَّن. --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">🧾</span>{{ __('account.dashboard.last_order_title') }}</h2>

                @if ($lastOrder)
                    <div class="co-line" style="padding-top:0">
                        <span>{{ __('account.orders.col_number') }}</span>
                        <span class="v co-mono">{{ $lastOrder->order_number }}</span>
                    </div>
                    <div class="co-line">
                        <span>{{ __('account.orders.col_date') }}</span>
                        <span class="v">{{ $lastOrder->created_at?->translatedFormat('Y/m/d') }}</span>
                    </div>
                    <div class="co-line">
                        <span>{{ __('account.orders.col_status') }}</span>
                        <span class="co-badge {{ $statusClass }}">{{ __('payment.status.' . $lastOrder->status) }}</span>
                    </div>
                    <div class="co-line total">
                        <span>{{ __('account.orders.col_total') }}</span>
                        <span class="v">{{ $money($lastOrder->grand_total) }} {{ __('common.currency') }}</span>
                    </div>

                    <div class="co-actions">
                        <a class="btn btn-primary" href="{{ route('customer.orders.show', ['order' => $lastOrder->id]) }}">{{ __('account.dashboard.last_order_view') }}</a>
                    </div>

                    <p class="co-hint" style="margin-top:12px">
                        {{ trans_choice('account.dashboard.orders_count', $ordersCount, ['count' => $ordersCount]) }}
                    </p>
                @else
                    <p class="co-lead">{{ __('account.dashboard.last_order_none') }}</p>
                    <div class="co-actions">
                        <a class="btn btn-primary" href="{{ route('books.index') }}">🛍️ {{ __('account.dashboard.last_order_none_cta') }}</a>
                    </div>
                @endif
            </div>

            {{-- تنقّل الحساب --}}
            <nav class="co-card" aria-label="{{ __('account.nav.aria') }}">
                <div class="co-actions">
                    <a class="btn btn-ghost" href="{{ route('customer.orders.index') }}">📦 {{ __('account.dashboard.all_orders') }}</a>
                    <a class="btn btn-ghost" href="{{ route('customer.profile.edit') }}">👤 {{ __('account.dashboard.edit_profile') }}</a>
                </div>

                <form method="POST" action="{{ route('customer.logout') }}" style="margin-top:14px">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-block">{{ __('account.logout.submit') }}</button>
                </form>
            </nav>

        </div>
    </div>
@endsection
