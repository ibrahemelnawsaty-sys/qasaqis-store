@extends('layouts.app')


@section('title', __('account.password.request_title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @include('partials.submit-guard', [
        'formId' => 'forgotForm',
        'buttonId' => 'forgotSubmit',
        'busyLabel' => __('account.password.sending'),
    ])

    @php
        // رقم واتساب المتجر — يُستخرج بنفس طريقة مكوّن wa-button وصفحة الشكر.
        $waNumber = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));
        $waHref = 'https://wa.me/' . $waNumber . '?text=' . rawurlencode(__('account.support.wa_message'));
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:460px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">🔑</div>
                <h1>{{ __('account.password.request_heading') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('account.password.request_lead') }}</p>

                @if (session('status'))
                    <div class="co-alert ok" role="status" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                    </div>
                @endif

                {{-- يُستعمل أيضًا لرسالة تعذّر إرسال البريد (account.password.mail_unavailable):
                     لا تُبتلع الرسالة بصمت فتظن الأم أن رابطًا في طريقه إليها. --}}
                @if (session('error'))
                    <div class="co-alert bad" role="alert" style="margin-top:14px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                    </div>
                @endif

                <form id="forgotForm" method="POST" action="{{ route('customer.password.email') }}" style="margin-top:6px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="fp-email">{{ __('account.password.email') }}</label>
                        <input id="fp-email" type="email" name="email" value="{{ old('email') }}"
                            maxlength="191" class="co-input @error('email') err @enderror"
                            placeholder="{{ __('account.password.email_ph') }}"
                            dir="ltr" autocomplete="email" required
                            @error('email') aria-invalid="true" aria-describedby="fp-email-err" @enderror>
                        @error('email')
                            <p class="co-err" id="fp-email-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-actions">
                        <button id="forgotSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account.password.send') }}</button>
                    </div>
                </form>

                <p class="co-hint" style="margin-top:16px;text-align:center;font-size:14px">
                    <a href="{{ route('customer.login.show') }}" style="color:var(--purple);font-weight:800">{{ __('account.password.back_to_login') }}</a>
                </p>
            </div>

            {{-- مسار دعم بشري صريح ودائم الظهور: البريد هو قناة الاسترداد الوحيدة
                 اليوم، وإرساله الفعلي غير مُثبت تشغيليًا بعد (بند 1.5). --}}
            @if (filled($waNumber))
                <div class="co-card" style="margin-top:16px">
                    <h2><span class="n" aria-hidden="true">💬</span>{{ __('account.support.title') }}</h2>
                    <p class="co-lead">{{ __('account.support.lead') }}</p>
                    <div class="co-actions">
                        <a class="btn btn-wa btn-block" href="{{ $waHref }}" target="_blank" rel="noopener noreferrer">
                            {{ __('account.support.whatsapp') }}
                        </a>
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
