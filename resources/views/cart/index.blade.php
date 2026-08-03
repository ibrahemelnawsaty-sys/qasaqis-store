@extends('layouts.app')


@section('title', __('checkout.cart.title') . ' — ' . __('common.brand'))

{{-- صفحة السلة: محتوى خاص بالجلسة، لا قيمة لها في الفهرس. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @php
        // خريطة سلة الجلسة الحاليّة {book_id: qty} (بعد إسقاط غير المتاح في buildSessionCart).
        $cartSessionMap = [];
        foreach ($cart->items as $cartItem) {
            $cartSessionMap[$cartItem->book->id] = $cartItem->quantity;
        }
    @endphp

    {{-- سلة المتصفّح الحقيقيّة تعيش في localStorage (الدرج). صفحة /cart تعرض سلة الجلسة
         الخادميّة (بأسعار قاعدة البيانات والكوبون). إن أضاف العميل كتبًا ولم تُزامَن بعد،
         نزامنها هنا مرّةً ونعيد التحميل فتظهر فورًا — بلا حاجة للضغط على «إتمام الطلب».
         حارس بصمة (sessionStorage) يمنع أي تكرار (مثلًا حين تُصفّي الجلسة كتابًا غير متاح). --}}
    <script>
        (function () {
            try {
                var raw = localStorage.getItem('qasaqis-cart');
                var local = raw ? JSON.parse(raw) : [];
                if (!Array.isArray(local) || !local.length) return;

                var localMap = {};
                local.forEach(function (i) { if (i && i.id) localMap[i.id] = Math.min(99, i.qty || 1); });

                var sess = @js((object) $cartSessionMap);
                var lk = Object.keys(localMap).sort();
                var sk = Object.keys(sess).sort();
                var same = lk.length === sk.length && lk.every(function (k) { return String(sess[k]) === String(localMap[k]); });
                if (same) return; // الجلسة تطابق المتصفّح → اعرض كما هو

                var sig = JSON.stringify(lk.map(function (k) { return k + ':' + localMap[k]; }));
                if (sessionStorage.getItem('qasaqis-cart-reconciled') === sig) return; // زامنّا هذه السلة سلفًا

                // لا نُعيد التحميل إلا إذا ثبت ضبط الحارس، وإلّا (تخزين معطّل) لتفادي أي تكرار.
                var guarded = false;
                try { sessionStorage.setItem('qasaqis-cart-reconciled', sig); guarded = true; } catch (e) {}
                if (!guarded) return;

                document.documentElement.style.visibility = 'hidden'; // تفادي وميض «سلتك فارغة»
                var token = document.querySelector('meta[name=csrf-token]').content;
                var body = new FormData();
                lk.forEach(function (id, i) {
                    body.append('items[' + i + '][book_id]', id);
                    body.append('items[' + i + '][qty]', localMap[id]);
                });
                fetch(@js(route('cart.update')), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, Accept: 'text/html' },
                    body: body,
                    redirect: 'manual',
                })
                    .then(function () { location.reload(); })
                    .catch(function () { document.documentElement.style.visibility = ''; });
            } catch (e) {}
        })();
    </script>

    @include('partials.checkout-styles')
    @include('partials.checkout-scripts')

    @php
        $money = fn ($v) => number_format((float) $v, 0);
    @endphp

    <div class="co">
        <div class="wrap">

            {{-- تنبيهات فلاش --}}
            @if (session('status'))
                <div class="co-alert ok" role="status"><span class="ai" aria-hidden="true">✅</span>{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
            @endif

            <div class="co-head">
                <h1>{{ __('checkout.cart.title') }}</h1>
                <p>{{ __('checkout.cart.subtitle') }}</p>
            </div>

            @if ($cart->isEmpty())
                {{-- سلة فارغة --}}
                <div class="co-card empty-state">
                    <div class="em" aria-hidden="true">🛒</div>
                    <h2 style="font-size:20px;font-weight:900;margin-top:10px">{{ __('checkout.cart.empty_title') }}</h2>
                    <p style="color:var(--ink-soft);margin-top:8px">{{ __('checkout.cart.empty_desc') }}</p>
                    <div style="margin-top:18px">
                        <a class="btn btn-primary" href="{{ route('books.index') }}">{{ __('checkout.cart.empty_cta') }}</a>
                    </div>
                </div>
            @else
                @if (! empty($cart->ignoredBookIds))
                    <div class="co-alert notice" role="alert"><span class="ai" aria-hidden="true">ℹ️</span>{{ __('checkout.cart.ignored_note') }}</div>
                @endif

                @php
                    $lines = [];
                    foreach ($cart->items as $item) {
                        $lines[] = ['price' => (float) $item->unitPrice, 'qty' => $item->quantity];
                    }
                @endphp

                <div class="co-layout" x-data="cartPage({ lines: @js($lines) })">
                    {{-- عمود العناصر --}}
                    <div>
                        <form method="POST" action="{{ route('cart.update') }}">
                            @csrf
                            <div class="co-card">
                                <div class="co-items">
                                    @foreach ($cart->items as $i => $item)
                                        <div class="co-item">
                                            <a href="{{ route('books.show', $item->book) }}" aria-hidden="true" tabindex="-1">
                                                @include('partials.book-thumb', ['book' => $item->book])
                                            </a>
                                            <input type="hidden" name="items[{{ $i }}][book_id]" value="{{ $item->book->id }}">

                                            <div class="co-item-main">
                                                <a class="co-item-title" href="{{ route('books.show', $item->book) }}">{{ $item->book->title }}</a>
                                                <div class="co-item-meta">{{ $money($item->unitPrice) }} {{ __('common.currency') }}</div>
                                            </div>

                                            <div class="co-qty" role="group" aria-label="{{ __('checkout.cart.col_qty') }}">
                                                <button type="button" @click="dec({{ $i }})" aria-label="{{ __('checkout.cart.decrease') }}">&minus;</button>
                                                <input type="number" min="1" max="99" inputmode="numeric"
                                                    name="items[{{ $i }}][qty]" x-model.number="lines[{{ $i }}].qty"
                                                    aria-label="{{ __('checkout.cart.col_qty') }}">
                                                <button type="button" @click="inc({{ $i }})" aria-label="{{ __('checkout.cart.increase') }}">+</button>
                                            </div>

                                            <div class="co-item-price">
                                                <span x-text="fmt(lineTotal({{ $i }}))">{{ $money($item->lineTotal) }}</span> {{ __('common.currency') }}
                                            </div>

                                            <button type="button" class="co-remove" @click="remove($event.currentTarget)"
                                                title="{{ __('checkout.cart.remove') }}" aria-label="{{ __('checkout.cart.remove') }}">🗑️</button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="co-actions">
                                <button type="submit" class="btn btn-ghost">🔄 {{ __('checkout.cart.update') }}</button>
                                <a class="btn btn-ghost" href="{{ route('books.index') }}">← {{ __('checkout.cart.continue') }}</a>
                            </div>
                        </form>
                    </div>

                    {{-- عمود الملخّص + الكوبون --}}
                    <aside class="co-summary" x-data="couponBox({ url: '{{ route('coupon.apply') }}', csrf: document.querySelector('meta[name=csrf-token]').content, errorText: @js(__('checkout.coupon.network_error')) })">
                        <div class="co-card">
                            <h2><span class="n" aria-hidden="true">%</span>{{ __('checkout.coupon.label') }}</h2>
                            <div class="co-coupon-row">
                                <input type="text" class="co-input" x-model="code" maxlength="50"
                                    placeholder="{{ __('checkout.coupon.placeholder') }}"
                                    @keydown.enter.prevent="apply()" aria-label="{{ __('checkout.coupon.label') }}">
                                <button type="button" class="btn btn-primary" @click="apply()" :disabled="loading"
                                    x-text="loading ? @js(__('checkout.coupon.applying')) : @js(__('checkout.coupon.apply'))">{{ __('checkout.coupon.apply') }}</button>
                            </div>
                            <p class="co-coupon-msg" x-cloak x-show="message" :class="valid ? 'ok' : 'bad'" x-text="message"></p>
                            <template x-if="applied && applied.free_shipping">
                                <p class="co-coupon-msg ok" x-cloak>{{ __('checkout.coupon.free_shipping') }}</p>
                            </template>
                        </div>

                        <div class="co-card">
                            <h2><span class="n" aria-hidden="true">🧾</span>{{ __('checkout.summary.title') }}</h2>

                            <div class="co-line">
                                <span>{{ __('checkout.summary.subtotal') }}</span>
                                <span class="v"><span x-text="fmt(subtotal)">{{ $money($cart->subtotal) }}</span> {{ __('common.currency') }}</span>
                            </div>

                            <template x-if="applied">
                                <div class="co-line discount">
                                    <span>{{ __('checkout.summary.discount') }} <span x-text="applied ? '(' + applied.code + ')' : ''"></span></span>
                                    <span class="v">−<span x-text="fmt(applied.discount)"></span> {{ __('common.currency') }}</span>
                                </div>
                            </template>

                            <div class="co-line">
                                <span>{{ __('checkout.summary.shipping') }}</span>
                                <span class="v">{{ __('checkout.summary.shipping_pending') }}</span>
                            </div>

                            <p class="co-hint">{{ __('checkout.summary.shipping_note') }}</p>

                            <a class="btn btn-primary btn-block" style="margin-top:14px"
                                :href="applied ? '{{ route('checkout.show') }}?coupon=' + encodeURIComponent(applied.code) : '{{ route('checkout.show') }}'"
                                href="{{ route('checkout.show') }}">🛍️ {{ __('checkout.cart.checkout') }}</a>
                        </div>
                    </aside>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            function cartPage(data) {
                return {
                    lines: data.lines,
                    fmt(v) { return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(v || 0); },
                    lineTotal(i) { return this.lines[i].price * this.lines[i].qty; },
                    get subtotal() { return this.lines.reduce((s, l) => s + l.price * l.qty, 0); },
                    inc(i) { this.lines[i].qty = Math.min(99, (this.lines[i].qty || 0) + 1); },
                    dec(i) { this.lines[i].qty = Math.max(1, (this.lines[i].qty || 1) - 1); },
                    // الإزالة تضبط قيمة الحقل مباشرةً في DOM (لا نعتمد على جدولة Alpine) ثم ترسل النموذج.
                    remove(btn) {
                        const item = btn.closest('.co-item');
                        const input = item ? item.querySelector('input[type="number"]') : null;
                        if (input) { input.value = 0; }
                        btn.closest('form').submit();
                    },
                };
            }
        </script>
    @endpush
@endsection
