{{-- تنويه العملة للزائر الأجنبيّ في مسار الدفع (السلة/الدفع): يظهر فقط حين عملة العرض
     ليست الأساس (الجنيه). $currency مُشارَكة عبر View::share في وسيط SetCurrency.
     يستعمل تنسيق تنبيهات الدفع (co-alert) المضمّن في checkout-styles. --}}
@if (isset($currency) && ! $currency->isBase())
    <div class="co-alert notice" role="note">
        <span class="ai" aria-hidden="true">🌍</span>{{ __('checkout.foreign_payment_notice', ['currency' => $currency->name_ar]) }}
    </div>
@endif
