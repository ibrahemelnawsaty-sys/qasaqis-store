@extends('layouts.app')


@section('title', __('account.login.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    @include('partials.submit-guard', [
        'formId' => 'loginForm',
        'buttonId' => 'loginSubmit',
        'busyLabel' => __('account.login.submitting'),
    ])

    <div class="co">
        <div class="wrap" style="max-width:460px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">👋</div>
                <h1>{{ __('account.login.heading') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account.login.lead') }}</p>

                @if (session('status'))
                    <div class="co-alert ok" role="status" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                    </div>
                @endif

                {{-- رسالة الفشل موحّدة (auth.failed) وتُعرض مجمّعة بلا نسبتها لحقل
                     بعينه، كي لا تصير الصفحة قناة تعداد تكشف أي الأرقام لها حساب.
                     لذلك يُعلَّم الحقلان معًا بحالة الخطأ لا أحدهما. --}}
                @if ($errors->any())
                    <div class="co-alert bad" role="alert" style="margin-top:14px;align-items:flex-start">
                        <span class="ai" aria-hidden="true">⚠️</span>
                        <span id="login-err">
                            @foreach ($errors->all() as $message)
                                <span style="display:block">{{ $message }}</span>
                            @endforeach
                        </span>
                    </div>
                @endif

                <form id="loginForm" method="POST" action="{{ route('customer.login.store') }}" style="margin-top:6px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="log-phone">{{ __('account.login.phone') }}</label>
                        <input id="log-phone" type="tel" name="phone" value="{{ old('phone') }}"
                            maxlength="20" class="co-input @if ($errors->any()) err @endif"
                            placeholder="{{ __('account.login.phone_ph') }}"
                            inputmode="tel" autocomplete="username" dir="ltr" required
                            @if ($errors->any()) aria-invalid="true" aria-describedby="login-err" @endif>
                    </div>

                    {{-- كلمة المرور بزر عين: تُقلّل احتكاك الإدخال على الجوال (M12). --}}
                    <div class="co-field" x-data="{ show: false }">
                        <label class="co-label" for="log-password">{{ __('account.login.password') }}</label>
                        <div class="acc-passwrap">
                            <input id="log-password" :type="show ? 'text' : 'password'" name="password"
                                class="co-input @if ($errors->any()) err @endif"
                                autocomplete="current-password" dir="ltr" required
                                @if ($errors->any()) aria-invalid="true" aria-describedby="login-err" @endif>
                            <button type="button" class="acc-eye" x-cloak @click="show = !show"
                                :aria-label="show ? @js(__('account.a11y.hide_password')) : @js(__('account.a11y.show_password'))"
                                :aria-pressed="show ? 'true' : 'false'">
                                <span x-show="!show" aria-hidden="true">👁️</span>
                                <span x-show="show" aria-hidden="true" x-cloak>🙈</span>
                            </button>
                        </div>
                    </div>

                    {{-- مربّع اختيار بمساحة لمس كاملة: التسمية نفسها هدف النقر (بند 6.3). --}}
                    <div class="co-field">
                        <label class="co-label" for="log-remember"
                            style="display:flex;align-items:center;gap:10px;min-height:44px;font-weight:700;cursor:pointer">
                            <input id="log-remember" type="checkbox" name="remember" value="1"
                                @checked(old('remember'))
                                style="width:20px;height:20px;accent-color:var(--purple);flex:0 0 auto">
                            {{ __('account.login.remember') }}
                        </label>
                    </div>

                    <div class="co-actions">
                        <button id="loginSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account.login.submit') }}</button>
                    </div>
                </form>

                <p class="co-hint" style="margin-top:16px;text-align:center;font-size:14px">
                    <a href="{{ route('customer.password.request') }}" style="color:var(--purple);font-weight:800">{{ __('account.login.forgot') }}</a>
                </p>

                <p class="co-hint" style="margin-top:10px;text-align:center;font-size:14px">
                    {{ __('account.login.no_account') }}
                    <a href="{{ route('customer.register.show') }}" style="color:var(--purple);font-weight:800">{{ __('account.login.no_account_link') }}</a>
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
