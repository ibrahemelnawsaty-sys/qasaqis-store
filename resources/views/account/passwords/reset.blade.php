@extends('layouts.app')


@section('title', __('account.password.reset_title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    @include('partials.submit-guard', [
        'formId' => 'resetForm',
        'buttonId' => 'resetSubmit',
        'busyLabel' => __('account.password.reset_submitting'),
    ])

    @php
        // البريد يصل مع رابط الاستعادة (?email=…). يُعرض للقراءة كي تعرف الأم أي
        // حساب تُعيد ضبطه، وليجد مدير كلمات المرور اسم المستخدم فيربط الزوج.
        // يبقى قابلًا للتحرير إن وصل الرابط بلا بريد، فلا يُغلق الطريق في وجهها.
        $resetEmail = old('email', $email ?? request()->query('email'));
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:460px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">🔒</div>
                <h1>{{ __('account.password.reset_heading') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account.password.reset_lead') }}</p>

                @if (session('error'))
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                    </div>
                @endif

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

                <form id="resetForm" method="POST" action="{{ route('customer.password.update') }}" style="margin-top:6px">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <div class="co-field">
                        <label class="co-label" for="rp-email">{{ __('account.password.email') }}</label>
                        <input id="rp-email" type="email" name="email" value="{{ $resetEmail }}"
                            maxlength="191" class="co-input @error('email') err @enderror"
                            placeholder="{{ __('account.password.email_ph') }}"
                            dir="ltr" autocomplete="username" required @readonly(filled($resetEmail))
                            @error('email') aria-invalid="true" aria-describedby="rp-email-err" @enderror>
                        @error('email')
                            <p class="co-err" id="rp-email-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- كلمة المرور بزر عين + مقياس قوة محلي (تحسين تدريجي): التقييم
                         واجهيّ لطمأنة العميلة فقط، والتحقّق الحاكم يبقى خادميًّا. --}}
                    <div class="co-field" x-data="{ show: false, value: '',
                        get lvl() { const p = this.value; if (! p) return 0; let s = 0;
                            if (p.length >= 8) s++; if (p.length >= 12) s++;
                            if (/[0-9]/.test(p) && /[A-Za-z]/.test(p)) s++; if (/[^A-Za-z0-9]/.test(p)) s++;
                            return s <= 1 ? 1 : (s === 2 ? 2 : 3); } }">
                        <label class="co-label" for="rp-password">{{ __('account.password.new_password') }}</label>
                        <div class="acc-passwrap">
                            <input id="rp-password" :type="show ? 'text' : 'password'" name="password" minlength="8"
                                class="co-input @error('password') err @enderror"
                                autocomplete="new-password" dir="ltr" required x-model="value"
                                aria-describedby="rp-password-hint @error('password') rp-password-err @enderror"
                                @error('password') aria-invalid="true" @enderror>
                            <button type="button" class="acc-eye" x-cloak @click="show = !show"
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
                        <p class="co-hint" id="rp-password-hint">{{ __('account.register.password_hint') }}</p>
                        @error('password')
                            <p class="co-err" id="rp-password-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="rp-password-confirmation">{{ __('account.password.new_password_confirmation') }}</label>
                        <input id="rp-password-confirmation" type="password" name="password_confirmation"
                            minlength="8" class="co-input @error('password') err @enderror"
                            autocomplete="new-password" dir="ltr" required>
                    </div>

                    <div class="co-actions">
                        <button id="resetSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account.password.reset_submit') }}</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
