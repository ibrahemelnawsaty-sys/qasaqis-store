@extends('layouts.app')

@section('title', __('account.verify.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')
    @include('partials.account-styles')

    @include('partials.submit-guard', [
        'formId' => 'verifyForm',
        'buttonId' => 'verifySubmit',
        'busyLabel' => __('account.verify.submit'),
    ])

    @php $codeLen = (int) config('verification.code_length', 6); @endphp

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

                    {{-- خانات الرمز: حقلٌ حقيقي واحد (يحفظ ملء one-time-code التلقائي
                         والّلصق) تعلوه ٦ خانات مرئية حين يعمل Alpine. بلا JS يظهر الحقل
                         نفسه مركزيًّا وقابلًا للإدخال — لا تُحجب المهمّة خلف نص برمجي. --}}
                    <div class="co-field">
                        <label class="co-label" for="f-code">{{ __('account.verify.code_label') }}</label>
                        {{-- dir=ltr على الحاوية: الرمز يُقرأ يسار→يمين (كرسالة SMS)، ولولاه
                             لرتّبت شبكة الخانات نفسها من اليمين في سياق RTL فيظهر معكوسًا. --}}
                        <div class="otp @error('code') is-error @enderror" dir="ltr" style="--n:{{ $codeLen }}"
                            x-data="{ n: {{ $codeLen }}, value: '' }" x-init="$el.classList.add('js')">
                            <input id="f-code" type="text" name="code"
                                inputmode="numeric" autocomplete="one-time-code"
                                maxlength="{{ $codeLen }}" dir="ltr"
                                class="co-input otp-input @error('code') err @enderror" required autofocus
                                :value="value"
                                @input="value = $event.target.value.replace(/[^0-9]/g, '').slice(0, n)">
                            <div class="otp-cells" x-cloak aria-hidden="true">
                                <template x-for="i in n" :key="i">
                                    <div class="otp-cell" :class="{ filled: value.length >= i, active: value.length === i - 1 || (value.length === n && i === n) }">
                                        <span x-text="value[i - 1] || ''"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
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
