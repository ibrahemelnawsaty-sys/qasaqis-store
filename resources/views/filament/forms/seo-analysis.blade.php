{{--
    نقاط تحليل SEO المباشر (نظير Yoast). تُصيَّر من ContentSeoAnalysis عبر Placeholder،
    وتُعاد مع كل تحديث Livewire. CSS خالص يتبع الوضع الداكن.
    @var \Illuminate\Support\Collection $checks
    @var array{status:string,label:string} $verdict
--}}
@php
    $dot = ['good' => '#059669', 'ok' => '#D97706', 'bad' => '#DC2626'];
    $vColor = $dot[$verdict['status']] ?? '#6E6280';
@endphp
<div class="seoa" style="--good:#059669;--ok:#D97706;--bad:#DC2626;">
    <style>
        .seoa { font-size:13px; color:#2E2440; display:flex; flex-direction:column; gap:8px; }
        .dark .seoa, .fi-theme-dark .seoa { color:#F1E9FA; }
        .seoa-verdict { display:inline-flex; align-items:center; gap:8px; font-weight:900; font-size:13.5px; padding:6px 14px; border-radius:999px; align-self:flex-start; border:1px solid currentColor; }
        .seoa-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
        .seoa-item { display:flex; align-items:flex-start; gap:9px; line-height:1.6; }
        .seoa-item .d { flex:0 0 auto; width:9px; height:9px; border-radius:50%; margin-top:6px; }
        .seoa-item .d.good { background:var(--good); }
        .seoa-item .d.ok { background:var(--ok); }
        .seoa-item .d.bad { background:var(--bad); }
        .seoa-item .t { flex:1 1 auto; min-width:0; }
    </style>

    <span class="seoa-verdict" style="color:{{ $vColor }}">
        <span style="width:10px;height:10px;border-radius:50%;background:{{ $vColor }};display:inline-block"></span>
        التقييم العام: {{ $verdict['label'] }}
    </span>

    <ul class="seoa-list">
        @foreach ($checks as $check)
            <li class="seoa-item">
                <span class="d {{ $check->status }}" aria-hidden="true"></span>
                <span class="t">{{ $check->message }}</span>
            </li>
        @endforeach
    </ul>
</div>
