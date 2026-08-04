@extends('emails.layout')

@section('kicker', 'سلتك بانتظارك')

@section('content')
    {{-- نصّ الرسالة يكتبه/يراجعه الأدمن (يتضمّن التحيّة والإجمالي وكود الخصم إن وُجد).
         {{ }} تُهرّب أي HTML فلا يُحقن، و white-space:pre-line يحفظ الأسطر. --}}
    <div style="font-size:15px;line-height:1.9;white-space:pre-line;color:#372a46;margin:0 0 22px;">{{ $body }}</div>

    <div style="text-align:center;margin:24px 0 8px;">
        <a href="{{ url('/') }}" style="display:inline-block;background:#6E2FB0;color:#ffffff;font-weight:800;font-size:15px;text-decoration:none;padding:13px 32px;border-radius:999px;">أكملي طلبك 🛍️</a>
    </div>

    <p style="font-size:13px;line-height:1.7;color:#a99fb6;margin:20px 0 0;text-align:center;">مع تحيات فريق <strong>قصاقيص أطفال</strong> 💛</p>
@endsection
