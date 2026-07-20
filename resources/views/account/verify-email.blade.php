@extends('layouts.app')

@section('title', __('account.verify.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @include('partials.submit-guard', [
        'formId' => 'verifyForm',
        'buttonId' => 'verifySubmit',
        'busyLabel' => __('account.verify.submit'),
    ])

    <div class="co">
        <div class="wrap" style="max-width:460px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">📧</div>
                <h1>{{ __('account.verify.title') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account.verify.intro', ['length' => (int) config('verification.code_length', 6), 'email' => $email]) }}</p>

                @if (session('status'))
                    <div class="co-alert ok" role="status" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                    </div>
                @endif

                @if (session('verify_error'))
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('verify_error') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ $errors->first('code') }}
                    </div>
                @endif

                <form id="verifyForm" method="POST" action="{{ route('customer.verify.store') }}" style="margin-top:16px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="f-code">{{ __('account.verify.code_label') }}</label>
                        <input id="f-code" type="text" name="code"
                            inputmode="numeric" autocomplete="one-time-code"
                            maxlength="{{ (int) config('verification.code_length', 6) }}"
                            dir="ltr" style="text-align:center;letter-spacing:.4em;font-size:1.4em"
                            class="co-input @error('code') err @enderror" required autofocus>
                    </div>

                    <button id="verifySubmit" type="submit" class="btn btn-primary btn-block" style="margin-top:14px">
                        {{ __('account.verify.submit') }}
                    </button>
                </form>

                {{-- إعادة الإرسال نموذج POST مستقلّ (throttle على المسار). --}}
                <form method="POST" action="{{ route('customer.verify.resend') }}" style="margin-top:12px;text-align:center">
                    @csrf
                    <button type="submit" class="btn btn-ghost" style="font-size:13.5px">
                        {{ __('account.verify.resend') }}
                    </button>
                </form>

                <div style="text-align:center;margin-top:12px">
                    <a href="{{ route('customer.dashboard') }}" style="font-size:13.5px;color:var(--ink-soft);text-decoration:none">
                        {{ __('account.verify.skip') }} ←
                    </a>
                </div>
            </div>

        </div>
    </div>
@endsection
