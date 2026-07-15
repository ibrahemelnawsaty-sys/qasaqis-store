@extends('layouts.app')

@section('title', __('payment.track.title') . ' — ' . __('common.brand'))

@section('content')
    @include('partials.checkout-styles')

    <div class="co">
        <div class="wrap" style="max-width:520px">

            <div class="co-hero">
                <div class="em" aria-hidden="true">🔎</div>
                <h1>{{ __('payment.track.title') }}</h1>
            </div>

            <div class="co-card" style="margin-top:16px">
                <p class="co-lead">{{ __('payment.track.lead') }}</p>

                @if (session('error'))
                    <div class="co-alert bad" role="alert"><span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}</div>
                @endif

                <form method="post" action="{{ route('orders.track.lookup') }}" style="margin-top:6px">
                    @csrf

                    <div class="co-field">
                        <label class="co-label" for="order_number">{{ __('payment.track.order_number_label') }}</label>
                        <input class="co-input @error('order_number') err @enderror" id="order_number"
                            name="order_number" type="text" dir="ltr" autocomplete="off" value="{{ old('order_number') }}"
                            placeholder="{{ __('payment.track.order_number_ph') }}" aria-describedby="trk-help" required>
                        @error('order_number')
                            <p class="co-coupon-msg bad" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="phone">{{ __('payment.track.phone_label') }}</label>
                        <input class="co-input @error('phone') err @enderror" id="phone" name="phone"
                            type="tel" dir="ltr" autocomplete="tel"
                            placeholder="{{ __('payment.track.phone_ph') }}" required>
                        @error('phone')
                            <p class="co-coupon-msg bad" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <p id="trk-help" class="co-lead" style="font-size:13px">{{ __('payment.track.help') }}</p>

                    <div class="co-actions">
                        <button class="btn btn-primary" type="submit">🔎 {{ __('payment.track.submit') }}</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
