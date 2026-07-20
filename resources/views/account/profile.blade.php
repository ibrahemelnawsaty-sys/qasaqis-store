@extends('layouts.app')


@section('title', __('account.profile.title') . ' — ' . __('common.brand'))

{{-- صفحات الحساب أدوات خاصة بالعميلة، لا قيمة لها في فهرس البحث. --}}
@section('seo_robots', 'noindex, follow')

@section('content')
    @include('partials.checkout-styles')

    @include('partials.submit-guard', [
        'formId' => 'profileForm',
        'buttonId' => 'profileSubmit',
        'busyLabel' => __('account.profile.submitting'),
    ])

    @php
        $customer = auth('customer')->user();

        // رقم الجوال يُعرض للقراءة فقط ولا يُرسل مع النموذج إطلاقًا: هو هوية الحساب
        // ومفتاح الدخول، وتعديله الذاتي بوابة استيلاء. phone_e164 قد يكون فارغًا
        // فتُعرض الصيغة المطبّعة المخزّنة كما هي — بلا إعادة تنسيق مخترعة.
        $phoneDisplay = filled($customer->phone_e164) ? $customer->phone_e164 : $customer->phone_normalized;

        // نفس قائمة المحافظات التي يتحقق منها CheckoutRequest (config('egypt.governorates'))،
        // كي يطابق العنوان المحفوظ خيارات صفحة الدفع ولا ينكسر الملء المسبق بصمت.
        $governorates = config('egypt.governorates', []);

        $hasSavedAddress = filled($customer->last_governorate)
            || filled($customer->last_city)
            || filled($customer->last_address_line);
    @endphp

    <div class="co">
        <div class="wrap" style="max-width:620px">

            <p style="margin-bottom:10px">
                <a href="{{ route('customer.dashboard') }}" style="font-size:13.5px;color:var(--ink-soft);text-decoration:none">
                    <span aria-hidden="true">←</span> {{ __('account.nav.dashboard') }}
                </a>
            </p>

            @if (session('status'))
                <div class="co-alert ok" role="status">
                    <span class="ai" aria-hidden="true">✅</span>{{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="co-alert bad" role="alert">
                    <span class="ai" aria-hidden="true">⚠️</span>{{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="co-alert bad" role="alert" style="align-items:flex-start">
                    <span class="ai" aria-hidden="true">⚠️</span>
                    <span>
                        @foreach ($errors->all() as $message)
                            <span style="display:block">{{ $message }}</span>
                        @endforeach
                    </span>
                </div>
            @endif

            <div class="co-head">
                <h1>{{ __('account.profile.heading') }}</h1>
                <p>{{ __('account.profile.lead') }}</p>
            </div>

            <form id="profileForm" method="POST" action="{{ route('customer.profile.update') }}">
                @csrf
                @method('PUT')

                {{-- البيانات الشخصية --}}
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">1</span>{{ __('account.profile.section_personal') }}</h2>

                    <div class="co-field">
                        <label class="co-label" for="pf-name">{{ __('account.profile.name') }}</label>
                        <input id="pf-name" type="text" name="name" value="{{ old('name', $customer->name) }}"
                            minlength="2" maxlength="150" class="co-input @error('name') err @enderror"
                            autocomplete="name" required
                            @error('name') aria-invalid="true" aria-describedby="pf-name-err" @enderror>
                        @error('name')
                            <p class="co-err" id="pf-name-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- عرض فقط — لا حقل إدخال، فلا يصل الجوال إلى الخادم من هذا النموذج أصلًا. --}}
                    <div class="co-field">
                        <dl class="co-dl" style="margin-top:0">
                            <dt>{{ __('account.profile.phone') }}</dt>
                            <dd class="co-mono">{{ $phoneDisplay }}</dd>
                        </dl>
                        <p class="co-hint">{{ __('account.profile.phone_locked') }}</p>
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pf-email">{{ __('account.profile.email') }}</label>
                        <input id="pf-email" type="email" name="email" value="{{ old('email', $customer->email) }}"
                            maxlength="191" class="co-input @error('email') err @enderror"
                            dir="ltr" autocomplete="email" required
                            aria-describedby="pf-email-hint @error('email') pf-email-err @enderror"
                            @error('email') aria-invalid="true" @enderror>
                        <p class="co-hint" id="pf-email-hint">{{ __('account.profile.email_hint') }}</p>
                        @error('email')
                            <p class="co-err" id="pf-email-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- عنوان التوصيل الافتراضي.
                     أسماء الحقول مطابقة لـ CheckoutRequest (governorate / city / address)
                     لتستفيد من تسميات attributes الموجودة في validation.php، ويحفظها
                     الـ Form Request في أعمدة last_* — كما يحفظ الدفع address في address_line. --}}
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">2</span>{{ __('account.profile.section_address') }}</h2>
                    <p class="co-lead" style="margin-bottom:14px">{{ __('account.profile.address_lead') }}</p>

                    @unless ($hasSavedAddress)
                        <p class="co-hint" style="margin-bottom:14px">{{ __('account.profile.address_empty') }}</p>
                    @endunless

                    <div class="co-field">
                        <label class="co-label" for="pf-governorate">
                            {{ __('account.profile.governorate') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <select id="pf-governorate" name="governorate"
                            class="co-select @error('governorate') err @enderror"
                            @error('governorate') aria-invalid="true" aria-describedby="pf-governorate-err" @enderror>
                            <option value="">{{ __('checkout.form.governorate_ph') }}</option>
                            @foreach ($governorates as $gov)
                                <option value="{{ $gov }}" @selected(old('governorate', $customer->last_governorate) === $gov)>{{ $gov }}</option>
                            @endforeach
                        </select>
                        @error('governorate')
                            <p class="co-err" id="pf-governorate-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pf-city">
                            {{ __('account.profile.city') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <input id="pf-city" type="text" name="city" value="{{ old('city', $customer->last_city) }}"
                            maxlength="80" class="co-input @error('city') err @enderror"
                            @error('city') aria-invalid="true" aria-describedby="pf-city-err" @enderror>
                        @error('city')
                            <p class="co-err" id="pf-city-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pf-address">
                            {{ __('account.profile.address_line') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <textarea id="pf-address" name="address" maxlength="300" rows="3"
                            class="co-textarea @error('address') err @enderror"
                            placeholder="{{ __('account.profile.address_ph') }}"
                            @error('address') aria-invalid="true" aria-describedby="pf-address-err" @enderror>{{ old('address', $customer->last_address_line) }}</textarea>
                        @error('address')
                            <p class="co-err" id="pf-address-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- تغيير كلمة المرور — اختياري داخل النموذج نفسه، لأن العقد يخصّص
                     مسارًا واحدًا لتحديث البيانات (customer.profile.update). --}}
                <div class="co-card">
                    <h2><span class="n" aria-hidden="true">3</span>{{ __('account.password.change_title') }}</h2>

                    <div class="co-field">
                        <label class="co-label" for="pf-current-password">
                            {{ __('account.password.current_password') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <input id="pf-current-password" type="password" name="current_password"
                            class="co-input @error('current_password') err @enderror"
                            autocomplete="current-password" dir="ltr"
                            @error('current_password') aria-invalid="true" aria-describedby="pf-current-password-err" @enderror>
                        @error('current_password')
                            <p class="co-err" id="pf-current-password-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pf-password">
                            {{ __('account.password.new_password') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <input id="pf-password" type="password" name="password" minlength="8"
                            class="co-input @error('password') err @enderror"
                            autocomplete="new-password" dir="ltr"
                            aria-describedby="pf-password-hint @error('password') pf-password-err @enderror"
                            @error('password') aria-invalid="true" @enderror>
                        <p class="co-hint" id="pf-password-hint">{{ __('account.register.password_hint') }}</p>
                        @error('password')
                            <p class="co-err" id="pf-password-err" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="co-field">
                        <label class="co-label" for="pf-password-confirmation">
                            {{ __('account.password.new_password_confirmation') }}
                            <span class="opt">{{ __('account.a11y.optional') }}</span>
                        </label>
                        <input id="pf-password-confirmation" type="password" name="password_confirmation"
                            minlength="8" class="co-input @error('password') err @enderror"
                            autocomplete="new-password" dir="ltr">
                    </div>
                </div>

                <div class="co-actions">
                    <button id="profileSubmit" type="submit" class="btn btn-primary btn-block">{{ __('account.profile.submit') }}</button>
                </div>
            </form>

        </div>
    </div>
@endsection
