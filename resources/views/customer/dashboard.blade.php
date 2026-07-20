@extends('layouts.app')

{{-- نقش خلفية دافئ يربط شاشات الحساب بصريًا --}}
@section('body_class', 'pat-scraps-confetti')

@section('title', __('account.dashboard.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    @php
        $money = fn ($v) => number_format((float) $v, 0);
        $initial = mb_strtoupper(mb_substr(trim((string) $customer->name) ?: '؟', 0, 1));

        // تصنيف حالة آخر طلب + إيموجي دلالي دافئ.
        $st = $lastOrder?->status;
        $statusTone = match ($st) {
            'delivered', 'completed' => 'ok',
            'cancelled', 'refused', 'refunded' => 'bad',
            default => 'wait',
        };
        $statusEmoji = match ($st) {
            'confirmed' => '✅', 'processing' => '📦', 'shipped' => '🚚',
            'delivered', 'completed' => '🎉',
            'cancelled', 'refused', 'refunded' => '⚠️',
            default => '🧾',
        };
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:680px">

            @if (session('status'))
                <div class="co-alert ok" role="status"><span class="ai" aria-hidden="true">✅</span>{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
            @endif

            {{-- تذكير غير حاجب: بريدك غير مؤكّد بعد (M9). --}}
            @if ($customer->email_verified_at === null)
                <div class="co-alert notice" role="status" style="align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                    <span><span class="ai" aria-hidden="true">📧</span>{{ __('account.verify.unverified_banner') }}</span>
                    <a class="btn btn-ghost" href="{{ route('customer.verify.show') }}" style="font-size:13.5px">{{ __('account.verify.title') }}</a>
                </div>
            @endif

            {{-- الهيرو الشخصي: مونوغرام + تحية بالاسم + عضوية + إحصائيات --}}
            <div class="acc-hero">
                <div class="acc-mono" aria-hidden="true">{{ $initial }}</div>
                <h1>{{ __('account.dashboard.greeting', ['name' => $customer->name]) }}</h1>
                <p>{{ __('account.dashboard.lead') }}</p>
                @if ($customer->created_at)
                    <span class="acc-memb">{{ __('account.dashboard.member_since', ['date' => $customer->created_at->translatedFormat('F Y')]) }}</span>
                @endif

                <div class="acc-stats">
                    <div class="acc-stat"><b>{{ $ordersCount }}</b><span>{{ __('account.dashboard.stat_orders') }}</span></div>
                    <div class="acc-stat"><b>{{ $booksCount }}</b><span>{{ __('account.dashboard.stat_library') }}</span></div>
                    <div class="acc-stat"><b aria-hidden="true">{{ $lastOrder ? $statusEmoji : __('account.dashboard.stat_last_none') }}</b><span>{{ __('account.dashboard.stat_last') }}</span></div>
                </div>
            </div>

            <div style="height:14px"></div>

            {{-- بطاقة آخر طلب — بغلاف وحالة بشرية، أو حالة فارغة مبهجة --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">🧾</span>{{ __('account.dashboard.last_order_title') }}</h2>

                @if ($lastOrder)
                    <div class="acc-lastcard" style="margin-top:6px">
                        <span class="acc-cover" aria-hidden="true">📖</span>
                        <div class="acc-last-main">
                            <div class="ot">{{ __('payment.status.' . $lastOrder->status) }}</div>
                            <div class="meta">{{ $lastOrder->order_number }} · {{ $lastOrder->created_at?->translatedFormat('Y/m/d') }}</div>
                            <span class="acc-status {{ $statusTone }}"><span aria-hidden="true">{{ $statusEmoji }}</span>{{ $money($lastOrder->grand_total) }} {{ __('common.currency') }}</span>
                        </div>
                    </div>
                    <div class="co-actions" style="margin-top:14px">
                        <a class="btn btn-primary" href="{{ route('customer.orders.show', ['order' => $lastOrder->id]) }}">{{ __('account.dashboard.last_order_view') }}</a>
                    </div>
                @else
                    <div class="acc-empty">
                        <div class="em" aria-hidden="true">📚</div>
                        <h3>{{ __('account.dashboard.empty_heading') }}</h3>
                        <p>{{ __('account.dashboard.empty_lead') }}</p>
                        <a class="btn btn-primary" href="{{ route('books.index') }}">🛍️ {{ __('account.dashboard.last_order_none_cta') }}</a>
                    </div>
                @endif
            </div>

            <div style="height:14px"></div>

            {{-- شبكة تنقّل بطاقات --}}
            <nav class="acc-nav" aria-label="{{ __('account.nav.aria') }}">
                <a class="acc-nav-item" href="{{ route('customer.orders.index') }}">
                    <span class="ic" aria-hidden="true">📦</span>
                    <b>{{ __('account.dashboard.all_orders') }}</b>
                    <span>{{ __('account.dashboard.nav_orders_sub') }}</span>
                </a>
                <a class="acc-nav-item" href="{{ route('customer.profile.edit') }}">
                    <span class="ic" aria-hidden="true">✏️</span>
                    <b>{{ __('account.dashboard.edit_profile') }}</b>
                    <span>{{ __('account.dashboard.nav_profile_sub') }}</span>
                </a>
            </nav>

            {{-- خروج مفصول بصريًا (فعل ثانوي) --}}
            <form method="POST" action="{{ route('customer.logout') }}" style="margin-top:16px;text-align:center">
                @csrf
                <button type="submit" class="btn btn-ghost" style="font-size:13.5px">{{ __('account.logout.submit') }}</button>
            </form>

        </div>
    </div>
@endsection
