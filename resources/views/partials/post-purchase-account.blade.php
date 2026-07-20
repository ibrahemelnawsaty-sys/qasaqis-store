{{--
    نافذة إنشاء حساب بعد الشراء (M10). تظهر تلقائيًا في صفحة الشكر إن كان الطلب
    غير مربوط والزائرة ضيفة ومفتاح جلسة الشراء يطابق هذا الطلب. كل البيانات من
    الطلب — لا تُدخل العميلة إلا كلمة المرور. Alpine خفيف. النصوص من الترجمة (6.4).

    التوسيط بفئات CSS لا بأنماط inline (إصلاح M11): x-show يبدّل style.display، فلو
    كان التوسيط (display:flex) مكتوبًا inline لمسحه x-show عند العرض فتلتصق النافذة
    بالحافّة (كما ظهر على اللاب توب). بوضعه في فئة يعود x-show ليبدّل flex↔none بأمان.
--}}
@php
    // تظهر فقط لجلسة المشتري نفسه (M10): طلب غير مربوط + ضيفة + مفتاح جلسة الشراء
    // يطابق هذا الطلب. رابط شكر مُسرَّب في متصفح آخر لا يملك المفتاح فلا نافذة له.
    $showAccountPopup = $order->customer_id === null
        && auth('customer')->guest()
        && (int) session(\App\Http\Controllers\Customer\PostPurchaseAccountController::SESSION_KEY) === (int) $order->id;
    $ppEmail = (string) ($order->customer_email ?? '');
@endphp

@if ($showAccountPopup)
    @include('partials.account-styles')
    <style>
        .pp-overlay { position: fixed; inset: 0; background: rgba(0, 0, 0, .55); z-index: 1000; }
        .pp-center {
            position: fixed; inset: 0; z-index: 1001;
            display: flex; align-items: center; justify-content: center;
            padding: 16px; overflow-y: auto;
        }
        .pp-modal {
            width: 100%; max-width: 440px; max-height: 92vh; overflow-y: auto;
            position: relative; margin: auto;
        }
        .pp-close { position: absolute; inset-inline-end: 12px; inset-block-start: 12px; z-index: 2; }
    </style>

    <div x-data="{ open: true }" x-cloak>
        {{-- الخلفية المعتمة --}}
        <div class="pp-overlay" x-show="open" x-transition.opacity @click="open = false"></div>

        {{-- حاوية التوسيط (flex في CSS كي لا يمسحه x-show) --}}
        <div class="pp-center" x-show="open" x-transition role="dialog" aria-modal="true" aria-labelledby="pp-title">
            <div class="co-card pp-modal" @click.stop>

                <button type="button" class="icon-btn pp-close" @click="open = false"
                    aria-label="{{ __('account.post_purchase.later') }}">✕</button>

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
                            <div class="co-input" dir="ltr" style="background:var(--surface-soft);cursor:default">{{ $ppEmail }}</div>
                        </div>
                    @else
                        <div class="co-field">
                            <label class="co-label" for="pp-email">{{ __('account.post_purchase.email_prompt') }}</label>
                            <input id="pp-email" type="email" name="email" value="{{ old('email') }}" maxlength="191"
                                class="co-input @error('email') err @enderror" dir="ltr" autocomplete="email" required>
                        </div>
                    @endif

                    <div class="co-field" x-data="{ show: false }">
                        <label class="co-label" for="pp-password">{{ __('account.post_purchase.password') }}</label>
                        <div class="acc-passwrap">
                            <input id="pp-password" :type="show ? 'text' : 'password'" name="password" minlength="8"
                                class="co-input @error('password') err @enderror" autocomplete="new-password" required>
                            <button type="button" class="acc-eye" @click="show = !show"
                                :aria-label="show ? @js(__('account.a11y.hide_password')) : @js(__('account.a11y.show_password'))"
                                :aria-pressed="show ? 'true' : 'false'">
                                <span x-show="!show" aria-hidden="true">👁️</span>
                                <span x-show="show" aria-hidden="true" x-cloak>🙈</span>
                            </button>
                        </div>
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
