@php
    $waNumber = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? ''));
@endphp

{{-- سلة محلية (localStorage) — واجهة فقط؛ الطلب النهائي يُرسَل عبر واتساب --}}
<template x-teleport="body">
    <div x-show="$store.cart.open" x-cloak>
        <div class="drawer-backdrop" @click="$store.cart.open = false" x-transition.opacity></div>
        <aside class="cart-panel" x-transition role="dialog" aria-modal="true" aria-label="{{ __('common.cart_title') }}">
            <div class="ch">
                <strong>{{ __('common.cart_title') }} <span x-text="$store.cart.count ? '(' + $store.cart.count + ')' : ''"></span></strong>
                <button type="button" class="icon-btn" @click="$store.cart.open = false"
                    aria-label="{{ __('common.close') }}">✕</button>
            </div>

            <div class="cart-items">
                <template x-if="$store.cart.items.length === 0">
                    <p class="cart-empty">{{ __('common.cart_empty') }}</p>
                </template>

                <template x-for="item in $store.cart.items" :key="item.id">
                    <div class="cart-item">
                        <div style="flex:1">
                            <a :href="item.url" class="book-title" style="font-size:14px" x-text="item.title"></a>
                            <div style="font-size:13px;color:var(--purple);font-weight:800"
                                x-show="item.price" x-text="(item.qty > 1 ? item.qty + ' × ' : '') + item.price"></div>
                        </div>
                        <button type="button" class="icon-btn" @click="$store.cart.remove(item.id)"
                            aria-label="{{ __('common.cart_remove') }}" style="width:36px;height:36px">🗑️</button>
                    </div>
                </template>
            </div>

            {{--
                مساران للطلب من نفس سلة localStorage:
                  • «إتمام الطلب والدفع» (cartCheckout.go): يزامن العناصر (id+qty فقط،
                    الأسعار خادمية — بند 4.1) إلى سلة الجلسة عبر cart.update ثم ينتقل
                    إلى checkout (دفع أونلاين/يدوي/COD).
                  • «الطلب عبر واتساب»: مسار سريع بلا خادم يقرأ نفس السلة مباشرة.
            --}}
            <div class="cart-foot" x-show="$store.cart.items.length > 0" x-cloak
                x-data="cartCheckout({ updateUrl: @js(route('cart.update')), checkoutUrl: @js(route('checkout.show')) })">
                <p style="font-size:12.5px;color:var(--ink-soft);margin-bottom:10px">{{ __('common.cart_note') }}</p>

                <button type="button" class="btn btn-primary btn-block" @click="go()" :disabled="submitting"
                    x-text="submitting ? @js(__('common.cart_checkout_loading')) : @js(__('common.cart_checkout'))">{{ __('common.cart_checkout') }}</button>

                <a class="btn btn-wa btn-block" style="margin-top:8px" :href="$store.cart.whatsappHref('{{ $waNumber }}')"
                    target="_blank" rel="noopener">{{ __('common.cart_checkout_wa') }}</a>

                <button type="button" class="btn btn-ghost btn-block" style="margin-top:8px"
                    @click="$store.cart.clear()">{{ __('common.cart_clear') }}</button>
            </div>
        </aside>
    </div>
</template>
