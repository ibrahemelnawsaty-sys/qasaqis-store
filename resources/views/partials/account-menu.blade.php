{{-- قائمة الحساب في الهيدر (تُدرَج في .nav-tools) بثلاث حالات:
     زائر → أيقونة دخول (مثل أيقونتَي الوضع/السلة تمامًا) · عميل → صورة + قائمة ·
     أدمن → درع + لوحة + خروج. تعيد استخدام مسارات customer.* ولوحة Filament.

     الأنماط inline (لا @push('head')): الهيدر يُصيَّر بعد إغلاق @stack('head') فلا
     يصل الدفع إليه. الأيقونات مقاسها في وسم <svg> نفسه (width/height) لا في CSS،
     تمامًا كأيقونات x-ui-icon — فتظهر بحجمها الصحيح دون اعتماد على تحميل نمط. --}}
@php
    $acctCustomer = auth('customer')->user();
    $acctAdmin = auth('web')->user();
    $acctInitial = static fn (?string $name): string => mb_strtoupper(mb_substr(trim((string) $name) ?: '؟', 0, 1));
@endphp

@once
    <style>
        .acct{ position:relative; display:inline-flex; }
        .acct-avatar{ display:grid; place-items:center; width:27px; height:27px; border-radius:50%;
            font-weight:900; font-size:13.5px; color:#fff; background:linear-gradient(135deg,var(--purple),var(--pink)); }
        .acct-avatar--admin{ background:linear-gradient(135deg,var(--teal),var(--purple)); }
        .acct-menu{ position:absolute; inset-inline-end:0; top:calc(100% + 12px); z-index:70; min-width:226px;
            background:var(--surface); border:1px solid var(--line); border-radius:var(--r-md);
            box-shadow:0 20px 44px -14px rgba(55,42,70,.4); padding:8px; }
        .acct-head{ display:flex; align-items:center; gap:10px; padding:6px 8px 12px; border-bottom:1px solid var(--line); margin-bottom:6px; }
        .acct-head .acct-avatar{ width:42px; height:42px; font-size:18px; }
        .acct-name{ font-weight:800; font-size:14.5px; color:var(--ink); line-height:1.2; }
        .acct-sub{ font-size:12px; color:var(--ink-soft); margin-top:2px; }
        .acct-item{ display:flex; align-items:center; gap:10px; width:100%; text-align:start; padding:10px;
            border-radius:var(--r-sm); font-weight:700; font-size:14px; color:var(--ink); text-decoration:none;
            background:transparent; border:0; cursor:pointer; font-family:inherit; }
        .acct-item:hover{ background:var(--surface-soft); color:var(--purple); }
        .acct-item--danger{ color:#e23c3c; }
        .acct-item--danger:hover{ background:color-mix(in srgb,#e23c3c 12%,var(--surface)); color:#e23c3c; }
        .acct-sep{ height:1px; background:var(--line); margin:6px 2px; }
    </style>
@endonce

@if ($acctCustomer)
    {{-- عميل: أيقونة الصورة (مثل زرّ الأيقونات) تفتح القائمة --}}
    <div class="acct" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="icon-btn" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="true" aria-label="{{ __('account_menu.aria') }}">
            <span class="acct-avatar">{{ $acctInitial($acctCustomer->name) }}</span>
        </button>
        <div class="acct-menu" x-show="open" x-transition.origin.top x-cloak role="menu">
            <div class="acct-head">
                <span class="acct-avatar">{{ $acctInitial($acctCustomer->name) }}</span>
                <div>
                    <div class="acct-name">{{ $acctCustomer->name }}</div>
                    <div class="acct-sub">{{ __('account_menu.customer') }}</div>
                </div>
            </div>
            <a class="acct-item" href="{{ route('customer.dashboard') }}" role="menuitem"><x-ui-icon name="home" :size="18" /> {{ __('account_menu.dashboard') }}</a>
            <a class="acct-item" href="{{ route('customer.orders.index') }}" role="menuitem"><x-ui-icon name="truck" :size="18" /> {{ __('account_menu.orders') }}</a>
            <a class="acct-item" href="{{ route('customer.profile.edit') }}" role="menuitem"><x-ui-icon name="badge-check" :size="18" /> {{ __('account_menu.profile') }}</a>
            <div class="acct-sep"></div>
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button type="submit" class="acct-item acct-item--danger" role="menuitem">{{ __('account_menu.logout') }}</button>
            </form>
        </div>
    </div>
@elseif ($acctAdmin)
    {{-- أدمن (حارس web / جلسة Filament) --}}
    <div class="acct" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="icon-btn" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="true" aria-label="{{ __('account_menu.aria') }}">
            <span class="acct-avatar acct-avatar--admin"><x-ui-icon name="shield-check" :size="17" /></span>
        </button>
        <div class="acct-menu" x-show="open" x-transition.origin.top x-cloak role="menu">
            <div class="acct-head">
                <span class="acct-avatar acct-avatar--admin"><x-ui-icon name="shield-check" :size="22" /></span>
                <div>
                    <div class="acct-name">{{ $acctAdmin->name }}</div>
                    <div class="acct-sub">{{ __('account_menu.admin') }}</div>
                </div>
            </div>
            <a class="acct-item" href="{{ url('/admin') }}" role="menuitem"><x-ui-icon name="grid" :size="18" /> {{ __('account_menu.admin_panel') }}</a>
            <div class="acct-sep"></div>
            <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
                @csrf
                <button type="submit" class="acct-item acct-item--danger" role="menuitem">{{ __('account_menu.logout') }}</button>
            </form>
        </div>
    </div>
@else
    {{-- زائر: أيقونة دخول بسيطة فاخرة — نفس شكل أيقونتَي الوضع/السلة (icon-btn + SVG بمقاس مباشر) --}}
    <a class="icon-btn" href="{{ route('login') }}" aria-label="{{ __('account_menu.login') }}" title="{{ __('account_menu.login') }}">
        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:block">
            <path d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
        </svg>
    </a>
@endif
