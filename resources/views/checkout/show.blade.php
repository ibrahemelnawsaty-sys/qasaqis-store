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
                    <form id="checkoutForm" method="POST" action="{{ route('checkout.place') }}">
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

                        {{-- بيانات التواصل --}}
                        <div class="co-card">
                            <h2><span class="n" aria-hidden="true">1</span>{{ __('checkout.form.section_contact') }}</h2>

                            <div class="co-field">
                                <label class="co-label" for="f-name">{{ __('checkout.form.name') }}</label>
                                <input id="f-name" type="text" name="name" value="{{ old('name') }}" maxlength="150"
                                    class="co-input @error('name') err @enderror" placeholder="{{ __('checkout.form.name_ph') }}"
                                    autocomplete="name" required>
                                @error('name') <p class="co-err">{{ $message }}</p> @enderror
                            </div>

                            <div class="co-grid2">
                                <div class="co-field half">
                                    <label class="co-label" for="f-phone">{{ __('checkout.form.phone') }}</label>
                                    <input id="f-phone" type="tel" name="phone" value="{{ old('phone') }}" maxlength="20"
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
                                <label class="co-label" for="f-email">{{ __('checkout.form.email') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                                <input id="f-email" type="email" name="email" value="{{ old('email') }}" maxlength="191"
                                    class="co-input @error('email') err @enderror" dir="ltr" autocomplete="email">
                                @error('email') <p class="co-err">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- عنوان الشحن --}}
                        <div class="co-card" x-data="{ country: '{{ old('country_code', 'EG') }}' }">
                            <h2><span class="n" aria-hidden="true">2</span>{{ __('checkout.form.section_shipping') }}</h2>

                            <div class="co-field">
                                <label class="co-label" for="f-country">{{ __('checkout.form.country') }}</label>
                                <select id="f-country" name="country_code" x-model="country"
                                    class="co-select @error('country_code') err @enderror" required>
                                    @foreach ($countries as $c)
                                        <option value="{{ $c->iso_code }}" @selected(old('country_code', 'EG') === $c->iso_code)>{{ $c->name_ar }}</option>
                                    @endforeach
                                </select>
                                @error('country_code') <p class="co-err">{{ $message }}</p> @enderror
                            </div>

                            <div class="co-grid2">
                                {{-- محافظة (مصر فقط) --}}
                                <div class="co-field half" x-show="country === 'EG'" x-cloak>
                                    <label class="co-label" for="f-gov">{{ __('checkout.form.governorate') }}</label>
                                    <select id="f-gov" name="governorate" class="co-select @error('governorate') err @enderror" :required="country === 'EG'">
                                        <option value="" disabled @selected(! old('governorate'))>{{ __('checkout.form.governorate_ph') }}</option>
                                        @foreach ($governorates as $gov)
                                            <option value="{{ $gov }}" @selected(old('governorate') === $gov)>{{ $gov }}</option>
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
                                    <input id="f-city" type="text" name="city" value="{{ old('city') }}" maxlength="80"
                                        class="co-input @error('city') err @enderror">
                                    @error('city') <p class="co-err">{{ $message }}</p> @enderror
                                </div>
                            </div>

                            <div class="co-field">
                                <label class="co-label" for="f-address">{{ __('checkout.form.address') }}</label>
                                <textarea id="f-address" name="address" maxlength="300" class="co-textarea @error('address') err @enderror"
                                    placeholder="{{ __('checkout.form.address_ph') }}" autocomplete="street-address" required>{{ old('address') }}</textarea>
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
