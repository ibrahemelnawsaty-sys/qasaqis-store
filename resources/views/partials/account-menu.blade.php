{{-- قائمة الحساب في الهيدر (تُدرَج في .nav-tools).
     ثلاث حالات: زائر → زرّ دخول موحّد؛ عميل → صورة + قائمة؛ أدمن → لوحة + خروج.
     تعيد استخدام مسارات customer.* ولوحة Filament القائمة — بلا منطق مصادقة هنا.
     الأنماط مكتفية بذاتها (@once) بدل تلويث app.css المشترك. --}}
@php
    $acctCustomer = auth('customer')->user();
    $acctAdmin = auth('web')->user();
    $acctInitial = static fn (?string $name): string => mb_strtoupper(mb_substr(trim((string) $name) ?: '؟', 0, 1));
@endphp

@once
    @push('head')
        <style>
            .acct{ position:relative; }
            .acct-login{ padding:9px 16px; min-height:40px; gap:7px; font-size:14px; }
            .acct-login svg{ width:18px; height:18px; }
            .acct-btn{ display:grid; place-items:center; width:40px; height:40px; padding:0; border:0; background:transparent; cursor:pointer; border-radius:50%; }
            .acct-avatar{ display:grid; place-items:center; width:38px; height:38px; border-radius:50%; font-weight:900; font-size:16px; color:#fff;
                background:linear-gradient(135deg,var(--purple),var(--pink)); box-shadow:0 6px 14px -6px rgba(236,78,150,.6); border:2px solid var(--surface); }
            .acct-avatar--admin{ background:linear-gradient(135deg,var(--teal),var(--purple)); }
            .acct-btn:focus-visible{ outline:3px solid var(--purple); outline-offset:2px; }
            .acct-menu{ position:absolute; inset-inline-end:0; top:calc(100% + 10px); z-index:60; min-width:230px;
                background:var(--surface); border:1px solid var(--line); border-radius:var(--r-md);
                box-shadow:0 18px 40px -12px rgba(55,42,70,.35); padding:8px; }
            .acct-head{ display:flex; align-items:center; gap:10px; padding:8px 8px 12px; border-bottom:1px solid var(--line); margin-bottom:6px; }
            .acct-avatar--lg{ width:44px; height:44px; font-size:18px; border:0; }
            .acct-name{ font-weight:800; font-size:14.5px; color:var(--ink); line-height:1.2; }
            .acct-sub{ font-size:12px; color:var(--ink-soft); margin-top:2px; }
            .acct-item{ display:flex; align-items:center; gap:10px; width:100%; text-align:start; padding:10px 10px; border-radius:var(--r-sm);
                font-weight:700; font-size:14px; color:var(--ink); text-decoration:none; background:transparent; border:0; cursor:pointer; font-family:inherit; }
            .acct-item:hover{ background:var(--surface-soft); color:var(--purple); }
            .acct-item--danger{ color:#e23c3c; }
            .acct-item--danger:hover{ background:color-mix(in srgb,#e23c3c 10%,var(--surface)); color:#e23c3c; }
            .acct-sep{ height:1px; background:var(--line); margin:6px 2px; }
        </style>
    @endpush
@endonce

@if ($acctCustomer)
    {{-- عميل مسجَّل الدخول --}}
    <div class="acct" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="acct-btn" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="true" aria-label="{{ __('account_menu.aria') }}">
            <span class="acct-avatar">{{ $acctInitial($acctCustomer->name) }}</span>
        </button>
        <div class="acct-menu" x-show="open" x-transition.origin.top x-cloak role="menu">
            <div class="acct-head">
                <span class="acct-avatar acct-avatar--lg">{{ $acctInitial($acctCustomer->name) }}</span>
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
    {{-- أدمن مسجَّل الدخول (حارس web / جلسة Filament) --}}
    <div class="acct" x-data="{ open: false }" @keydown.escape.window="open = false" @click.outside="open = false">
        <button type="button" class="acct-btn" @click="open = !open" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="true" aria-label="{{ __('account_menu.aria') }}">
            <span class="acct-avatar acct-avatar--admin"><x-ui-icon name="shield-check" :size="18" /></span>
        </button>
        <div class="acct-menu" x-show="open" x-transition.origin.top x-cloak role="menu">
            <div class="acct-head">
                <span class="acct-avatar acct-avatar--admin acct-avatar--lg"><x-ui-icon name="shield-check" :size="20" /></span>
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
    {{-- زائر: زرّ دخول موحّد جميل بهوية الموقع --}}
    <a class="btn btn-primary acct-login" href="{{ route('login') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" />
        </svg>
        <span>{{ __('account_menu.login') }}</span>
    </a>
@endif
