@extends('layouts.app')


@section('title', __('account.register.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    {{-- الشبكات المصرية بطيئة: الضغطة الثانية تُنتج محاولة تسجيل مكرّرة تفشل
         بتصادم رقم الجوال وتُربك الأم. الحماية النهائية خادمية دائمًا. --}}
    @include('partials.submit-guard', [
        'formId' => 'registerForm',
        'buttonId' => 'registerSubmit',
        'busyLabel' => __('account.register.submitting'),
    ])

    <div class="co">
        <div class="wrap" style="max-width:520px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">🌟</div>
                <h1>{{ __('account.register.heading') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account.register.lead') }}</p>

                @if (session('error'))
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                    </div>
                @endif

                {{-- ملخّص الأخطاء أعلى النموذج: يُقرأ فورًا بقارئ الشاشة ويغني عن
                     التمرير بحثًا عن الحقل المعطوب على شاشة الهاتف (بند 6.3). --}}
                @if ($errors->any())
                    <div class="co-alert bad" role="alert" style="margin-top:14px;align-items:flex-start">
                        <span class="ai" aria-hidden="true">⚠️</span>
                        <span>
                            @foreach ($errors->all() as $message)
                                <span style="display:block">{{ $message }}</span>
                            @endforeach
                        </span>
                    </div>
                @endif

                <form id="registerForm" method="POST" action="{{ route('customer.register.store') }}" style="margin-top:6px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="reg-name">{{ __('account.register.name') }}</label>
                        <input id="reg-name" type="text" name="name" value="{{ old('name') }}"
                            minlength="2" maxlength="150" class="co-input @error('name') err @enderror"
                            placeholder="{{ __('account.register.name_ph') }}" autocomplete="name" required
                            @error('name') aria-invalid="true" aria-describedby="reg-name-err" @enderror>
                        @error('name')
                            <p class="co-err" id="reg-name-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- الجوال هو معرّف الدخول: autocomplete=username كي يحفظ مدير
                         كلمات المرور الزوج الصحيح، ولأنه غير قابل للتعديل لاحقًا. --}}
                    <div class="co-field">
                        <label class="co-label" for="reg-phone">{{ __('account.register.phone') }}</label>
                        <input id="reg-phone" type="tel" name="phone" value="{{ old('phone') }}"
                            maxlength="20" class="co-input @error('phone') err @enderror"
                            placeholder="{{ __('account.register.phone_ph') }}"
                            inputmode="tel" autocomplete="username" dir="ltr" required
                            aria-describedby="reg-phone-hint @error('phone') reg-phone-err @enderror"
                            @error('phone') aria-invalid="true" @enderror>
                        <p class="co-hint" id="reg-phone-hint">{{ __('account.register.phone_hint') }}</p>
                        @error('phone')
                            <p class="co-err" id="reg-phone-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="reg-email">{{ __('account.register.email') }}</label>
                        <input id="reg-email" type="email" name="email" value="{{ old('email') }}"
                            maxlength="191" class="co-input @error('email') err @enderror"
                            placeholder="{{ __('account.register.email_ph') }}"
                            dir="ltr" autocomplete="email" required
                            aria-describedby="reg-email-hint @error('email') reg-email-err @enderror"
                            @error('email') aria-invalid="true" @enderror>
                        <p class="co-hint" id="reg-email-hint">{{ __('account.register.email_hint') }}</p>
                        @error('email')
                            <p class="co-err" id="reg-email-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- كلمة المرور بزر عين + مقياس قوة محلي (تحسين تدريجي، لا يمسّ
                         التحقّق الخادمي). --}}
                    <div class="co-field" x-data="{ show: false, value: '',
                        get lvl() { const p = this.value; if (! p) return 0; let s = 0;
                            if (p.length >= 8) s++; if (p.length >= 12) s++;
                            if (/[0-9]/.test(p) && /[A-Za-z]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
                            return s <= 1 ? 1 : (s === 2 ? 2 : 3); } }">
                        <label class="co-label" for="reg-password">{{ __('account.register.password') }}</label>
                        <div class="acc-passwrap">
                            <input id="reg-password" :type="show ? 'text' : 'password'" name="password" minlength="8"
                                class="co-input @error('password') err @enderror"
                                autocomplete="new-password" dir="ltr" required x-model="value"
                                aria-describedby="reg-password-hint @error('password') reg-password-err @enderror"
                                @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="acc-eye" @click="show = !show"
                                :aria-label="show ? @js(__('account.a11y.hide_password')) : @js(__('account.a11y.show_password'))"
                                :aria-pressed="show ? 'true' : 'false'">
                                <span x-show="!show" aria-hidden="true">👁️</span>
                                <span x-show="show" aria-hidden="true" x-cloak>🙈</span>
                            </button>
                        </div>
                        <div class="acc-strength" x-cloak x-show="value.length > 0" aria-hidden="true">
                            <div class="bar"><i :class="{ w1: lvl === 1, w2: lvl === 2, w3: lvl === 3 }" :style="`width:${lvl * 33.34}%`"></i></div>
                            <div class="lbl" :class="{ w1: lvl === 1, w2: lvl === 2, w3: lvl === 3 }"
                                x-text="lvl === 1 ? @js(__('account.password.strength_weak')) : (lvl === 2 ? @js(__('account.password.strength_medium')) : @js(__('account.password.strength_strong')))"></div>
                        </div>
                        <p class="co-hint" id="reg-password-hint">{{ __('account.register.password_hint') }}</p>
                        @error('password')
                            <p class="co-err" id="reg-password-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- قاعدة confirmed تسجّل خطأها على مفتاح password، فلا رسالة منفصلة هنا. --}}
                    <div class="co-field">
                        <label class="co-label" for="reg-password-confirmation">{{ __('account.register.password_confirmation') }}</label>
                        <input id="reg-password-confirmation" type="password" name="password_confirmation"
                            minlength="8" class="co-input @error('password') err @enderror"
                            autocomplete="new-password" dir="ltr" required>
                    </div>

                    <div class="co-actions">
                        <button id="registerSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account.register.submit') }}</button>
                    </div>
                </form>

                {{-- توقّع صريح: التسجيل لا يسحب الطلبات السابقة تلقائيًا. --}}
                <p class="co-hint" style="margin-top:14px">{{ __('account.register.no_orders_note') }}</p>

                <p class="co-hint" style="margin-top:16px;text-align:center;font-size:14px">
                    {{ __('account.register.has_account') }}
                    <a href="{{ route('customer.login.show') }}" style="color:var(--purple);font-weight:800">{{ __('account.register.has_account_link') }}</a>
                </p>
            </div>

            {{-- الشراء والتتبّع يبقيان متاحين بلا حساب — لا نحجز خدمة خلف تسجيل. --}}
            <p class="co-hint" style="text-align:center;margin-top:14px">
                {{ __('account.login.guest_track') }}
                <a href="{{ route('orders.track.show') }}" style="color:var(--purple);font-weight:800">{{ __('account.login.guest_track_link') }}</a>
            </p>

        </div>
    </div>
@endsection
