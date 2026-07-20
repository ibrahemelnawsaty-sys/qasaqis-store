{{--
    نافذة إنشاء حساب بعد الشراء (M10). تظهر تلقائيًا في صفحة الشكر إن كان الطلب
    غير مربوط بحساب والزائرة ضيفة. كل البيانات من الطلب — لا تُدخل العميلة إلا كلمة
    المرور (والبريد فقط للطلبات القديمة التي سبقت إلزام البريد). Alpine خفيف على
    نمط cart-drawer القائم. النصوص من الترجمة (بند 6.4).
--}}
@php
    $showAccountPopup = $order->customer_id === null && auth('customer')->guest();
    $ppEmail = (string) ($order->customer_email ?? '');
@endphp

@if ($showAccountPopup)
    <div x-data="{ open: true }" x-cloak>
        <div class="drawer-backdrop" x-show="open" @click="open = false" x-transition.opacity
            style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:60"></div>

        <div x-show="open" x-transition
            role="dialog" aria-modal="true" aria-labelledby="pp-title"
            style="position:fixed;inset:0;z-index:61;display:flex;align-items:center;justify-content:center;padding:16px">
            <div class="co-card" style="max-width:420px;width:100%;max-height:90vh;overflow:auto;position:relative">

                <button type="button" class="icon-btn" @click="open = false"
                    aria-label="{{ __('account.post_purchase.later') }}"
                    style="position:absolute;inset-inline-end:12px;inset-block-start:12px">✕</button>

                <div style="text-align:center;margin-bottom:8px">
                    <div class="em" aria-hidden="true" style="font-size:2.2em">🎉</div>
                    <h2 id="pp-title" style="margin:6px 0">{{ __('account.post_purchase.heading') }}</h2>
                </div>

                <p class="co-lead" style="font-size:14px">{{ __('account.post_purchase.lead') }}</p>

                @if (session('error'))
                    <div class="co-alert bad" role="alert" style="margin-top:12px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="co-alert bad" role="alert" style="margin-top:12px">
                        <span class="ai" aria-hidden="true">⚠️</span>{{ $errors->first() }}
                    </div>
                @endif

                <form method="POST"
                    action="{{ \Illuminate\Support\Facades\URL::signedRoute('orders.create-account', ['order' => $order->id]) }}"
                    style="margin-top:14px">
                    @csrf

                    @if ($ppEmail !== '')
                        {{-- البريد من الطلب — يُعرض للقراءة، ويُؤخذ خادميًا لا من هذا الحقل. --}}
                        <div class="co-field">
                            <span class="co-label">{{ __('account.post_purchase.email_known') }}</span>
                            <div class="co-input" dir="ltr" style="background:var(--surface-sub);cursor:default">{{ $ppEmail }}</div>
                        </div>
                    @else
                        <div class="co-field">
                            <label class="co-label" for="pp-email">{{ __('account.post_purchase.email_prompt') }}</label>
                            <input id="pp-email" type="email" name="email" value="{{ old('email') }}" maxlength="191"
                                class="co-input @error('email') err @enderror" dir="ltr" autocomplete="email" required>
                        </div>
                    @endif

                    <div class="co-field">
                        <label class="co-label" for="pp-password">{{ __('account.post_purchase.password') }}</label>
                        <input id="pp-password" type="password" name="password" minlength="8"
                            class="co-input @error('password') err @enderror" autocomplete="new-password" required>
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pp-password-confirm">{{ __('account.post_purchase.password_confirmation') }}</label>
                        <input id="pp-password-confirm" type="password" name="password_confirmation" minlength="8"
                            class="co-input" autocomplete="new-password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">
                        {{ __('account.post_purchase.submit') }}
                    </button>
                </form>

                <div style="text-align:center;margin-top:10px">
                    <button type="button" class="btn btn-ghost" @click="open = false" style="font-size:13.5px">
                        {{ __('account.post_purchase.later') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
