@extends('layouts.app')

@section('title', __('account_menu.page.title') . ' — ' . __('common.brand'))

{{-- صفحة أداة (دخول) لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @include('partials.submit-guard', [
        'formId' => 'uauthForm',
        'buttonId' => 'uauthSubmit',
        'busyLabel' => __('account_menu.page.submit'),
    ])

    <div class="co">
        <div class="wrap" style="max-width:460px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">👋</div>
                <h1>{{ __('account_menu.page.heading') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account_menu.page.sub') }}</p>

                @if (session('status'))
                    <div class="co-alert ok" role="status" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                    </div>
                @endif

                {{-- رسالة الفشل موحّدة (auth.failed) وتُعرض مجمّعة بلا نسبتها لحقل بعينه،
                     كي لا تصير الصفحة قناة تعداد تكشف أي المُعرّفات لها حساب. --}}
                @if ($errors->any())
                    <div class="co-alert bad" role="alert" style="margin-top:14px;align-items:flex-start">
                        <span class="ai" aria-hidden="true">⚠️</span>
                        <span id="uauth-err">
                            @foreach ($errors->all() as $message)
                                <span style="display:block">{{ $message }}</span>
                            @endforeach
                        </span>
                    </div>
                @endif

                <form id="uauthForm" method="POST" action="{{ route('login.store') }}" style="margin-top:6px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="uauth-id">{{ __('account_menu.page.identifier') }}</label>
                        <input id="uauth-id" type="text" name="identifier" value="{{ old('identifier') }}"
                            maxlength="191" class="co-input @if ($errors->any()) err @endif"
                            placeholder="{{ __('account_menu.page.identifier_ph') }}"
                            autocomplete="username" dir="ltr" required
                            @if ($errors->any()) aria-invalid="true" aria-describedby="uauth-err" @endif>
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="uauth-password">{{ __('account_menu.page.password') }}</label>
                        <input id="uauth-password" type="password" name="password"
                            class="co-input @if ($errors->any()) err @endif"
                            autocomplete="current-password" dir="ltr" required
                            @if ($errors->any()) aria-invalid="true" aria-describedby="uauth-err" @endif>
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="uauth-remember"
                            style="display:flex;align-items:center;gap:10px;min-height:44px;font-weight:700;cursor:pointer">
                            <input id="uauth-remember" type="checkbox" name="remember" value="1"
                                @checked(old('remember'))
                                style="width:20px;height:20px;accent-color:var(--purple);flex:0 0 auto">
                            {{ __('account_menu.page.remember') }}
                        </label>
                    </div>

                    <div class="co-actions">
                        <button id="uauthSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account_menu.page.submit') }}</button>
                    </div>
                </form>

                <p class="co-hint" style="margin-top:16px;text-align:center;font-size:14px">
                    <a href="{{ route('customer.password.request') }}" style="color:var(--purple);font-weight:800">{{ __('account_menu.page.forgot') }}</a>
                </p>

                <p class="co-hint" style="margin-top:10px;text-align:center;font-size:14px">
                    {{ __('account_menu.page.no_account') }}
                    <a href="{{ route('customer.register.show') }}" style="color:var(--purple);font-weight:800">{{ __('account_menu.page.register') }}</a>
                </p>
            </div>

        </div>
    </div>
@endsection
