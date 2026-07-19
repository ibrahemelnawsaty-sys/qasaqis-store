@extends('layouts.app')

{{-- نقش خلفية دفع الطلب --}}
@section('body_class', 'pat-dots-and-arcs')

@section('title', __('checkout.payment.title') . ' — ' . __('common.brand'))

@section('content')
    @include('partials.checkout-styles')
    @include('partials.clear-local-cart')

    {{-- أرجح مواضع النقر المزدوج في المتجر كله: رفع صورة إثبات ثقيلة على شبكة
         بطيئة. مهلة أطول (90 ثانية) لأن الرفع نفسه يستغرق وقتًا — تحرير الزر بعد
         20 ثانية كان سيدعوها للنقر ثانيةً أثناء رفع جارٍ فعلًا. --}}
    @include('partials.submit-guard', [
        'formId' => 'proofForm',
        'buttonId' => 'proofSubmitBtn',
        'busyLabel' => __('checkout.payment.submitting'),
        'busyTimeout' => 90000,
    ])

    @php
        $money2 = fn ($v) => number_format((float) $v, 2);
        $thankyouUrl = \Illuminate\Support\Facades\URL::signedRoute('orders.thankyou', ['order' => $order->id]);
        $statusClasses = ['pending_review' => 'wait', 'approved' => 'paid', 'rejected' => 'bad'];
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:820px">

            @if (session('status'))
                <div class="co-alert ok" role="status"><span class="ai" aria-hidden="true">✅</span>{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
            @endif

            <div class="co-head">
                <h1>{{ __('checkout.payment.title') }}</h1>
                <p>{{ __('checkout.payment.subtitle') }}</p>
            </div>

            {{-- تعليمات التحويل --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">1</span>{{ $method?->name ?? __('checkout.payment.title') }}</h2>

                <div class="co-line" style="padding-top:0">
                    <span>{{ __('checkout.payment.order_number') }}</span>
                    <span class="v co-mono">{{ $order->order_number }}</span>
                </div>

                <div class="co-amount-box">
                    <div class="lbl">{{ __('checkout.payment.amount_due') }}</div>
                    <div class="val">{{ $money2($order->grand_total) }} <span style="font-size:.5em">{{ __('common.currency') }}</span></div>
                </div>

                @if ($method && filled($method->instructions))
                    <p style="font-size:14.5px;line-height:1.8;color:var(--ink-soft)">{{ $method->instructions }}</p>
                @endif

                @if ($method && filled($method->account_details))
                    <h3 style="font-weight:900;font-size:14.5px;margin:16px 0 4px">{{ __('checkout.payment.account_details') }}</h3>
                    <dl class="co-dl">
                        @foreach ($method->account_details as $label => $value)
                            <dt>{{ is_string($label) ? $label : '' }}</dt>
                            <dd>{{ is_array($value) ? implode(' — ', $value) : $value }}</dd>
                        @endforeach
                    </dl>
                @endif

                <p class="co-hint" style="margin-top:14px">💡 {{ __('checkout.payment.reference_hint') }}</p>
            </div>

            {{-- رفع الإثبات --}}
            <div class="co-card">
                <h2><span class="n" aria-hidden="true">2</span>{{ __('checkout.payment.upload_title') }}</h2>
                <p class="co-hint" style="margin-bottom:14px">{{ __('checkout.payment.upload_hint') }}</p>

                <form id="proofForm" method="POST" action="{{ $proofUrl }}" enctype="multipart/form-data">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="f-proof">{{ __('checkout.payment.file_label') }}</label>
                        <input id="f-proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                            class="co-file @error('proof') err @enderror" required>
                        @error('proof') <p class="co-err">{{ $message }}</p> @enderror
                    </div>

                    <div class="co-grid2">
                        <div class="co-field half">
                            <label class="co-label" for="f-amount">{{ __('checkout.payment.amount_label') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                            <input id="f-amount" type="number" step="0.01" min="0" max="999999.99" name="amount" dir="ltr"
                                value="{{ old('amount', $money2($order->grand_total)) }}" class="co-input @error('amount') err @enderror">
                            @error('amount') <p class="co-err">{{ $message }}</p> @enderror
                        </div>
                        <div class="co-field half">
                            <label class="co-label" for="f-ref">{{ __('checkout.payment.reference_label') }} <span class="opt">{{ __('checkout.form.optional') }}</span></label>
                            <input id="f-ref" type="text" name="sender_reference" value="{{ old('sender_reference') }}" maxlength="120"
                                class="co-input @error('sender_reference') err @enderror" placeholder="{{ __('checkout.payment.reference_ph') }}" dir="ltr">
                            @error('sender_reference') <p class="co-err">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="co-actions">
                        <button id="proofSubmitBtn" type="submit" class="btn btn-primary">📤 {{ __('checkout.payment.submit') }}</button>
                        <a class="btn btn-ghost" href="{{ $thankyouUrl }}">{{ __('checkout.payment.to_thankyou') }} ←</a>
                    </div>
                </form>
            </div>

            {{-- الإثباتات المرفوعة سابقًا --}}
            @if ($order->paymentProofs->isNotEmpty())
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">📎</span>{{ __('checkout.payment.existing_title') }}</h2>
                    <div class="co-items">
                        @foreach ($order->paymentProofs as $proof)
                            <div class="co-item">
                                <span class="co-thumb ph"><span class="co-thumb-i" aria-hidden="true">🧾</span></span>
                                <div class="co-item-main">
                                    <span class="co-item-title">{{ $money2($proof->amount) }} {{ __('common.currency') }}</span>
                                    <div class="co-item-meta">{{ __('checkout.payment.uploaded_at', ['date' => $proof->created_at?->translatedFormat('Y/m/d H:i')]) }}</div>
                                </div>
                                @php $st = $proof->review_status ?? 'pending_review'; @endphp
                                <span class="co-badge {{ $statusClasses[$st] ?? 'wait' }}">
                                    {{ __('checkout.payment.proof_status.' . $st) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection
