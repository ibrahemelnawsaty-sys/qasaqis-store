@extends('emails.layout')

@section('kicker', 'ردّ على استفسارك')

@section('content')
    <p style="font-size:16px;margin:0 0 14px;font-weight:800;">أهلًا {{ $inquiry->name }} 👋</p>
    <p style="font-size:14.5px;line-height:1.8;color:#6e6280;margin:0 0 18px;">شكرًا لتواصلك مع <strong>قصاقيص أطفال</strong>. سعدنا باستفسارك، وإليك ردّنا:</p>
    <div style="background:#f0e6fa;border-inline-start:4px solid #6E2FB0;border-radius:10px;padding:16px 18px;margin:0 0 18px;">
        <div style="font-size:12px;font-weight:800;color:#6E2FB0;margin-bottom:6px;">ردّ فريق قصاقيص أطفال</div>
        <div style="font-size:15px;line-height:1.9;white-space:pre-line;color:#372a46;">{{ $inquiry->admin_reply }}</div>
    </div>
    @if (filled($inquiry->message))
        <div style="font-size:12.5px;color:#a99fb6;margin:0 0 18px;">
            <div style="font-weight:700;margin-bottom:4px;">استفسارك الأصلي:</div>
            <div style="white-space:pre-line;line-height:1.7;">{{ \Illuminate\Support\Str::limit($inquiry->message, 500) }}</div>
        </div>
    @endif
    <p style="font-size:14px;line-height:1.8;color:#6e6280;margin:18px 0 0;">لو عندك أي استفسار آخر، تقدر ترد على هذا البريد أو تتواصل معنا مباشرة.<br>مع تحيات فريق <strong>قصاقيص أطفال</strong> 💛</p>
@endsection
