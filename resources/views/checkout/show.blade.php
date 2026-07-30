@extends('layouts.app')


@section('title', __('checkout.form.title') . ' — ' . __('common.brand'))

{{-- صفحة الدفع: خطوة معاملة خاصة بالجلسة، لا قيمة لها في الفهرس. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.checkout-scripts')

    @php
        $money = fn ($v) => number_format((float) $v, 0);
        $couponInitial = old('coupon', request('coupon', ''));
        $selectedMethod = old('payment_method', $methods->first()?->code);
    @endphp

    <div class="co">
        <div class="wrap">

            @if (session('error'))
                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
            @endif

            <div class="co-head">
                <h1>{{ __('checkout.form.title') }}</h1>
                <p>{{ __('checkout.form.subtitle') }}</p>
            </div>

            <div class="co-layout"
                x-data="couponBox({ url: '{{ route('coupon.apply') }}', csrf: document.querySelector('meta[name=csrf-token]').content, errorText: @js(__('checkout.coupon.network_error')), code: @js($couponInitial) })"
                x-init="if (code) apply()">

                {{-- عمود النموذج --}}
                <div>
                    <form id="checkoutForm" method="POST" action="{{ route('checkout.place') }}" enctype="multipart/form-data">
                        @csrf

                        {{-- حقول إسناد التتبّع (M6) — تُملأ من كوكيز المتصفح لحدث الشراء الخادمي. --}}
                        <input type="hidden" name="fbp" id="qs-fbp">
                        <input type="hidden" name="fbc" id="qs-fbc">
                        <input type="hidden" name="ga_client_id" id="qs-gacid">
                        <input type="hidden" name="ga_session_id" id="qs-gasid">
                        <input type="hidden" name="ads_consent" id="qs-consent" value="0">

                        {{-- عناصر الطلب (تُعاد تسعيرها من قاعدة البيانات على الخادم) --}}
                        @foreach ($cart->items as $i => $item)
                            <input type="hidden" name="items[{{ $i }}][book_id]" value="{{ $item->book->id }}">
                            <input type="hidden" name="items[{{ $i }}][qty]" value="{{ $item->quantity }}">
                        @endforeach

                        {{-- الخطوة ١: البيانات + اختيار طريقة الدفع. لطرق requires_proof يظهر زرّ
                             «متابعة للدفع» فينتقل JS إلى الخطوة ٢ (بيانات الطريقة + رفع الإثبات). --}}
                        <div id="co-step1">

                        {{-- محدِّد العنوان المحفوظ (للعميلة المسجّلة فقط): اختيار عنوان
                             يملأ النموذج أدناه بـJS، أو «عنوان جديد» يفرّغه. --}}
                        @if ($addresses->isNotEmpty())
                            <style>
                                .addr-picks{ display:flex; flex-direction:column; gap:10px; }
                                .addr-pick{ display:flex; gap:10px; align-items:flex-start; border:1px solid var(--line);
                                    border-radius:var(--r-md); padding:12px 14px; cursor:pointer; transition:border-color .12s ease; }
                                .addr-pick:has(input:checked){ border-color:var(--purple); box-shadow:0 0 0 2px var(--purple-soft); }
                                .addr-pick input{ margin-top:3px; width:18px; height:18px; accent-color:var(--purple); flex:none; }
                                .addr-pick .ap-body{ display:flex; flex-direction:column; gap:2px; min-width:0; }
                                .addr-pick b{ font-weight:800; }
                                .addr-pick .ap-def{ font-size:11px; font-weight:800; color:var(--purple);
                                    background:var(--purple-soft); border-radius:var(--r-pill); padding:1px 8px; margin-inline-start:6px; }
                                .addr-pick .ap-sub{ font-size:12.5px; color:var(--ink-soft); }
                            </style>
                            <div class="co-card">
                                <h2><span class="n" aria-hidden="true">📍</span>{{ __('account.address.section') }}</h2>
                                <p class="co-hint" style="margin-bottom:12px">{{ __('account.address.pick_hint') }}</p>
                                <div class="addr-picks">
                                    @foreach ($addresses as $addr)
                                        <label class="addr-pick">
                                            <input type="radio" name="__addr_pick" value="{{ $addr->id }}"
                                                @checked($addr->is_default) onchange="fillCheckoutAddress('{{ $addr->id }}')">
                                            <span class="ap-body">
                                                <span><b>{{ $addr->label }}</b>@if ($addr->is_default)<span class="ap-def">{{ __('account.address.default_badge') }}</span>@endif</span>
                                                <span class="ap-sub">{{ $addr->name }} · <span dir="ltr">{{ $addr->phone }}</span></span>
                                                <span class="ap-sub">{{ collect([$addr->governorate, $addr->state_province, $addr->city, $addr->address_line])->filter()->implode('، ') }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                    <label class="addr-pick">
                                        <input type="radio" name="__addr_pick" value="new" onchange="fillCheckoutAddress('new')">
                                        <span class="ap-body"><b>➕ {{ __('account.address.new') }}</b><span class="ap-sub">{{ __('account.address.new_hint') }}</span></span>
                                    </label>
                                </div>
                            </div>

                            @php
                                $addrMap = $addresses->mapWithKeys(fn ($a) => [(string) $a->id => [
                                    'f-name' => $a->name, 'f-phone' => $a->phone, 'f-phone-alt' => $a->phone_alt,
                                    'f-country' => $a->country_code, 'f-gov' => $a->governorate,
                                    'f-state' => $a->state_province, 'f-city' => $a->city,
                                    'f-address' => $a->address_line, 'f-anotes' => $a->address_notes,
                                ]])->all();
                            @endphp
                            <script>
                                window.CHECKOUT_ADDR = @json($addrMap);
                                function fillCheckoutAddress(id) {
                                    // «عنوان جديد» يُفرّغ النموذج لكن يُبقي الدولة مصر (لئلا ينقلب
                                    // إلى نموذج دوليّ بلا محافظة على الأم المصرية).
                                    var a = (id === 'new') ? { 'f-country': 'EG' } : (window.CHECKOUT_ADDR[id] || {});
                                    ['f-name', 'f-phone', 'f-phone-alt', 'f-country', 'f-gov', 'f-state', 'f-city', 'f-address', 'f-anotes'].forEach(function (fid) {
                                        var el = document.getElementById(fid);
                                        if (! el) { return; }
                                        el.value = a[fid] || '';
                                        el.dispatchEvent(new Event('input', { bubbles: true }));
                                        el.dispatchEvent(new Event('change', { bubbles: true }));
                                    });
                                }
                            </script>
                        @endif

                        {{-- بيانات التواصل --}}
                        <div class="co-card">
                            <h2><span class="n" aria-hidden="true">1</span>{{ __('checkout.form.section_contact') }}</h2>

                            <div class="co-field">
                                <label class="co-label" for="f-name">{{ __('checkout.form.name') }}</label>
                                <input id="f-name" type="text" name="name" value="{{ old('name', $prefill['name'] ?? '') }}" maxlength="150"
                                    class="co-input @error('name') err @enderror" placeholder="{{ __('checkout.form.name_ph') }}"
                                    autocomplete="name" required>
                                @error('name') <p class="co-err">{{ $message }}</p> @enderror
                            </div>

                            <div class="co-grid2">
                                <div class="co-field half">
                                    <label class="co-label" for="f-phone">{{ __('checkout.form.phone') }}</label>
                                    <input id="f-phone" type="tel" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" maxlength="20"
                                        class="co-input @error('phone') err @enderror" placeholder="{{ __('checkout.form.phone_ph') }}"
                                        inputmode="tel" autocomplete="tel" dir="ltr" required>
                                    @error('phone') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                                <div class="co-field half">
                                    <label class="co-label" for="f-phone-alt">{{ __('checkout.form.phone_alt') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                                    <input id="f-phone-alt" type="tel" name="phone_alt" value="{{ old('phone_alt') }}" maxlength="20"
                                        class="co-input @error('phone_alt') err @enderror" inputmode="tel" dir="ltr">
                                    @error('phone_alt') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="co-field">
                                <label class="co-label" for="f-email">{{ __('checkout.form.email') }}</label>
                                <input id="f-email" type="email" name="email" value="{{ old('email', $prefill['email'] ?? '') }}" maxlength="191"
                                    class="co-input @error('email') err @enderror" dir="ltr" autocomplete="email" required>
                                @error('email') <p class="co-err">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- عنوان الشحن --}}
                        <div class="co-card" x-data="{ country: '{{ old('country_code', $prefill['country_code'] ?? 'EG') }}' }">
                            <h2><span class="n" aria-hidden="true">2</span>{{ __('checkout.form.section_shipping') }}</h2>

                            <div class="co-field">
                                <label class="co-label" for="f-country">{{ __('checkout.form.country') }}</label>
                                <select id="f-country" name="country_code" x-model="country"
                                    class="co-select @error('country_code') err @enderror" required>
                                    @foreach ($countries as $c)
                                        <option value="{{ $c->iso_code }}" @selected(old('country_code', $prefill['country_code'] ?? 'EG') === $c->iso_code)>{{ $c->name_ar }}</option>
                                    @endforeach
                                </select>
                                @error('country_code') <p class="co-err">{{ $message }}</p> @enderror
                            </div>

                            <div class="co-grid2">
                                {{-- محافظة (مصر فقط) --}}
                                <div class="co-field half" x-show="country === 'EG'" x-cloak>
                                    <label class="co-label" for="f-gov">{{ __('checkout.form.governorate') }}</label>
                                    <select id="f-gov" name="governorate" class="co-select @error('governorate') err @enderror" :required="country === 'EG'">
                                        <option value="" disabled @selected(! old('governorate', $prefill['governorate'] ?? ''))>{{ __('checkout.form.governorate_ph') }}</option>
                                        @foreach ($governorates as $gov)
                                            <option value="{{ $gov }}" @selected(old('governorate', $prefill['governorate'] ?? '') === $gov)>{{ $gov }}</option>
                                        @endforeach
                                    </select>
                                    @error('governorate') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                                {{-- ولاية/إقليم (دولي) --}}
                                <div class="co-field half" x-show="country !== 'EG'" x-cloak>
                                    <label class="co-label" for="f-state">{{ __('checkout.form.state_province') }}</label>
                                    <input id="f-state" type="text" name="state_province" value="{{ old('state_province') }}" maxlength="100"
                                        class="co-input @error('state_province') err @enderror" placeholder="{{ __('checkout.form.state_province_ph') }}" :required="country !== 'EG'">
                                    @error('state_province') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                                <div class="co-field half">
                                    <label class="co-label" for="f-city">{{ __('checkout.form.city') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                                    <input id="f-city" type="text" name="city" value="{{ old('city', $prefill['city'] ?? '') }}" maxlength="80"
                                        class="co-input @error('city') err @enderror">
                                    @error('city') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="co-field">
                                <label class="co-label" for="f-address">{{ __('checkout.form.address') }}</label>
                                <textarea id="f-address" name="address" maxlength="300" class="co-textarea @error('address') err @enderror"
                                    placeholder="{{ __('checkout.form.address_ph') }}" autocomplete="street-address" required>{{ old('address', $prefill['address'] ?? '') }}</textarea>
                                @error('address') <p class="co-err">{{ $message }}</p> @enderror
                            </div>

                            <div class="co-field">
                                <label class="co-label" for="f-anotes">{{ __('checkout.form.address_notes') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                                <input id="f-anotes" type="text" name="address_notes" value="{{ old('address_notes') }}" maxlength="300"
                                    class="co-input @error('address_notes') err @enderror">
                                @error('address_notes') <p class="co-err">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- طريقة الدفع --}}
                        <div class="co-card">
                            <h2><span class="n" aria-hidden="true">3</span>{{ __('checkout.form.section_payment') }}</h2>

                            @if (! $onlineEnabled)
                                <div class="co-alert notice" role="status"><span class="ai" aria-hidden="true">💳</span>{{ __($onlineDisabledMessageKey) }}</div>
                            @endif

                            @if ($methods->isEmpty())
                                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ __('checkout.form.no_methods') }}</div>
                            @else
                                <div class="co-methods">
                                    @foreach ($methods as $method)
                                        <label class="co-method">
                                            <input type="radio" name="payment_method" value="{{ $method->code }}"
                                                data-requires-proof="{{ $method->requires_proof ? '1' : '0' }}"
                                                @checked($selectedMethod === $method->code) required>
                                            <span>
                                                <span class="mt">{{ $method->name }}</span>
                                                @if ($method->requires_proof)
                                                    <span class="md">{{ __('checkout.form.requires_proof_hint') }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('payment_method') <p class="co-err">{{ $message }}</p> @enderror
                            @endif

                            <div class="co-field" style="margin-top:16px;margin-bottom:0">
                                <label class="co-label" for="f-note">{{ __('checkout.form.note') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                                <textarea id="f-note" name="note" maxlength="1000" class="co-textarea @error('note') err @enderror"
                                    placeholder="{{ __('checkout.form.note_ph') }}">{{ old('note') }}</textarea>
                                @error('note') <p class="co-err">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        </div>{{-- /co-step1 --}}

                        {{-- الخطوة ٢: بيانات طريقة الدفع المختارة + رفع الإثبات. يظهرها JS عند الضغط
                             على «متابعة للدفع». الملفّ إجباريّ خادميًّا (CheckoutRequest) فلا يُنشأ طلب
                             تحويل يدويّ بلا إثبات. «المطلوب تحويله» = الإجمالي شامل الشحن الثابت
                             (overrides فارغة، والتحويل اليدويّ محلّيّ)، يُحسب حيًّا مع الكوبون في couponBox. --}}
                        @php
                            $proofMethods = $methods->filter(fn ($m) => $m->requires_proof);
                            $subtotalNum = (float) $cart->subtotal;
                            $flatShip = (float) config('egypt.shipping.flat', 0);
                        @endphp
                        @if ($proofMethods->isNotEmpty())
                            <div id="proofBlock" class="co-card" hidden>
                                <button type="button" id="backToStep1" class="co-back-step" style="background:none;border:0;cursor:pointer;color:var(--ink-soft);font:inherit;padding:0;margin-bottom:10px">← {{ __('checkout.form.back_to_data') }}</button>
                                <h2><span class="n" aria-hidden="true">🧾</span>{{ __('checkout.form.proof_section_title') }}</h2>

                                <div class="co-amount-box">
                                    <div class="lbl">{{ __('checkout.form.proof_transfer_amount') }}</div>
                                    <div class="val"
                                        x-text="fmt(Math.max(0, {{ $subtotalNum }} - (applied ? parseFloat(applied.discount) : 0) + ((applied &amp;&amp; applied.free_shipping) ? 0 : {{ $flatShip }}))) + ' {{ __('common.currency') }}'">{{ number_format(max(0, $subtotalNum + $flatShip), 0) }} {{ __('common.currency') }}</div>
                                </div>

                                @foreach ($proofMethods as $pm)
                                    <div class="proof-instr" data-method="{{ $pm->code }}" hidden>
                                        @include('partials.payment-details', ['method' => $pm])
                                    </div>
                                @endforeach

                                <div class="co-field" style="margin-top:14px">
                                    <label class="co-label" for="f-proof">{{ __('checkout.form.proof_field_label') }}</label>
                                    <input id="f-proof" type="file" name="proof"
                                        accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                        class="co-file @error('proof') err @enderror">
                                    <p class="co-hint" style="margin-top:6px">{{ __('checkout.form.proof_field_hint') }}</p>
                                    @error('proof') <p class="co-err">{{ $message }}</p> @enderror
                                </div>

                                <div class="co-grid2">
                                    <div class="co-field half">
                                        <label class="co-label" for="f-amount">{{ __('checkout.form.proof_amount_label') }}</label>
                                        <input id="f-amount" type="number" step="0.01" min="0" max="999999.99" name="amount" dir="ltr"
                                            value="{{ old('amount') }}" class="co-input @error('amount') err @enderror">
                                        @error('amount') <p class="co-err">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="co-field half">
                                        <label class="co-label" for="f-ref">{{ __('checkout.form.proof_ref_label') }}</label>
                                        <input id="f-ref" type="text" name="sender_reference" value="{{ old('sender_reference') }}" maxlength="120"
                                            class="co-input @error('sender_reference') err @enderror" placeholder="{{ __('checkout.form.proof_ref_ph') }}" dir="ltr">
                                        @error('sender_reference') <p class="co-err">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- عمود الملخّص --}}
                <aside class="co-summary">
                    <div class="co-card">
                        <h2><span class="n" aria-hidden="true">🧾</span>{{ __('checkout.summary.title') }}</h2>

                        <div class="co-items" style="margin-bottom:14px">
                            @foreach ($cart->items as $item)
                                <div class="co-item" style="padding:8px;border:none">
                                    @include('partials.book-thumb', ['book' => $item->book])
                                    <div class="co-item-main">
                                        <span class="co-item-title">{{ $item->book->title }}</span>
                                        <div class="co-item-meta">{{ $item->quantity }} × {{ $money($item->unitPrice) }} {{ __('common.currency') }}</div>
                                    </div>
                                    <div class="co-item-price">{{ $money($item->lineTotal) }} {{ __('common.currency') }}</div>
                                </div>
                            @endforeach
                        </div>

                        {{-- حقل الكوبون (يُرسل مع النموذج عبر form=) --}}
                        <div class="co-field">
                            <label class="co-label" for="f-coupon">{{ __('checkout.coupon.label') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                            <div class="co-coupon-row">
                                <input id="f-coupon" type="text" name="coupon" form="checkoutForm" class="co-input" maxlength="50"
                                    x-model="code" placeholder="{{ __('checkout.coupon.placeholder') }}"
                                    @keydown.enter.prevent="apply()">
                                <button type="button" class="btn btn-ghost" @click="apply()" :disabled="loading"
                                    x-text="loading ? @js(__('checkout.coupon.applying')) : @js(__('checkout.coupon.apply'))">{{ __('checkout.coupon.apply') }}</button>
                            </div>
                            <p class="co-coupon-msg" x-cloak x-show="message" :class="valid ? 'ok' : 'bad'" x-text="message"></p>
                        </div>

                        <div class="co-line">
                            <span>{{ __('checkout.summary.subtotal') }}</span>
                            <span class="v">{{ $money($cart->subtotal) }} {{ __('common.currency') }}</span>
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

                        {{-- زرّ الخطوة ١ للطرق التي تتطلّب إثباتًا (ينقل JS للخطوة ٢، لا يُرسل). --}}
                        <button id="continueBtn" type="button" class="btn btn-primary btn-block" style="margin-top:14px;display:none">{{ __('checkout.form.continue_to_pay') }} →</button>
                        <button id="placeOrderBtn" type="submit" form="checkoutForm" class="btn btn-primary btn-block" style="margin-top:14px"
                            @if ($methods->isEmpty()) disabled @endif>✅ {{ __('checkout.form.place_order') }}</button>

                        <div style="text-align:center;margin-top:12px">
                            <a href="{{ route('cart.show') }}" style="font-size:13.5px;color:var(--ink-soft);text-decoration:none">← {{ __('checkout.form.back_to_cart') }}</a>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </div>

    {{-- ملء حقول إسناد التتبّع (M6) من كوكيز المتصفح (best-effort؛ فارغة إن رفض الكوكيز). --}}
    @push('scripts')
        <script>
            (function () {
                function ck(n) { var m = document.cookie.match('(^|;)\\s*' + n + '\\s*=\\s*([^;]+)'); return m ? decodeURIComponent(m.pop()) : ''; }
                function set(id, v) { var el = document.getElementById(id); if (el && v) el.value = v; }
                set('qs-fbp', ck('_fbp'));
                var fbc = ck('_fbc');
                if (!fbc) { var p = new URLSearchParams(location.search).get('fbclid'); if (p) { fbc = 'fb.1.' + Date.now() + '.' + p; } }
                set('qs-fbc', fbc);
                var ga = ck('_ga');
                if (ga) { var parts = ga.split('.'); if (parts.length >= 4) { set('qs-gacid', parts[2] + '.' + parts[3]); } }
                // موافقة إعلانية صريحة → تُرسَل PII خادميًا لـ Meta CAPI (وإلا لا).
                try { if (localStorage.getItem('qs-consent') === 'granted') { document.getElementById('qs-consent').value = '1'; } } catch (e) {}
            })();
        </script>

    @endpush

    {{-- إظهار كتلة الإثبات لطرق requires_proof + جعل الملفّ إجباريًّا عند الظهور (والخادم
         يفرضه أيضًا)، وإظهار تعليمات الطريقة المختارة، وضغط صور الإيصال الكبيرة قبل الرفع
         (نت مصر). حارس الإرسال المزدوج آمن مع required (لا يُعطّل الزر إلا بعد نجاح تحقّق
         المتصفّح). --}}
    @push('scripts')
        <script>
            (function () {
                var block = document.getElementById('proofBlock');
                var step1 = document.getElementById('co-step1');
                if (!block || !step1) return;                        // لا طرق إثبات → صفحة واحدة كالمعتاد
                var contBtn = document.getElementById('continueBtn');
                var placeBtn = document.getElementById('placeOrderBtn');
                var backBtn = document.getElementById('backToStep1');
                var radios = document.querySelectorAll('input[name="payment_method"]');
                var file = document.getElementById('f-proof');
                var step = 1;

                function sel() { return document.querySelector('input[name="payment_method"]:checked'); }
                function needsProof() { var s = sel(); return !!(s && s.getAttribute('data-requires-proof') === '1'); }

                // الخطوة ١: البيانات + اختيار الطريقة. الخطوة ٢ (لطرق الإثبات فقط): بيانات الطريقة
                // + رفع الإثبات. الملفّ إجباريّ في الخطوة ٢ فقط (والخادم يفرضه أيضًا).
                function render() {
                    var onStep2 = step === 2 && needsProof();
                    step1.hidden = onStep2;
                    block.hidden = !onStep2;
                    if (file) file.required = onStep2;
                    if (onStep2) {
                        var s = sel();
                        block.querySelectorAll('[data-method]').forEach(function (d) {
                            d.hidden = !(s && d.getAttribute('data-method') === s.value);
                        });
                    }
                    if (contBtn) contBtn.style.display = (!onStep2 && needsProof()) ? '' : 'none';
                    if (placeBtn) placeBtn.style.display = (onStep2 || !needsProof()) ? '' : 'none';
                }

                if (contBtn) contBtn.addEventListener('click', function () {
                    var bad = step1.querySelector(':invalid');        // تحقّق حقول الخطوة ١ قبل الانتقال
                    if (bad) { if (bad.reportValidity) bad.reportValidity(); if (bad.focus) bad.focus(); return; }
                    step = 2; render();
                    block.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                if (backBtn) backBtn.addEventListener('click', function () {
                    step = 1; render();
                    step1.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
                // تغيير طريقة الدفع يعيد للخطوة ١ (اختيار جديد ⇒ بيانات/إثبات مختلفة).
                radios.forEach(function (r) { r.addEventListener('change', function () { step = 1; render(); }); });
                render();

                if (file) {
                    file.addEventListener('change', function () {
                        var f = file.files && file.files[0];
                        if (!f || f.type.indexOf('image/') !== 0 || f.size <= 1.5 * 1024 * 1024) return;
                        createImageBitmap(f).then(function (bmp) {
                            var max = 1600, w = bmp.width, h = bmp.height;
                            if (Math.max(w, h) > max) { var s = max / Math.max(w, h); w = Math.round(w * s); h = Math.round(h * s); }
                            var c = document.createElement('canvas'); c.width = w; c.height = h;
                            c.getContext('2d').drawImage(bmp, 0, 0, w, h);
                            c.toBlob(function (b) {
                                if (!b || b.size >= f.size) return;
                                var out = new File([b], (f.name || 'proof').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
                                var dt = new DataTransfer(); dt.items.add(out); file.files = dt.files;
                            }, 'image/jpeg', 0.82);
                        }).catch(function () {});
                    });
                }
            })();
        </script>
    @endpush

    {{-- منع الإرسال المزدوج (M7 — المرحلة 5). reloadOnRestore لأن الجلسة تحمل
         مفتاح المحاولة: صفحة مستعادة من ذاكرة المتصفح تبدو جاهزة بينما مفتاحها
         استُهلك بطلب مكتمل، فيُردّ الإرسال الثاني بصمت بالطلب القديم. --}}
    @include('partials.submit-guard', [
        'formId' => 'checkoutForm',
        'buttonId' => 'placeOrderBtn',
        'busyLabel' => __('checkout.form.placing'),
        'reloadOnRestore' => true,
    ])
@endsection
