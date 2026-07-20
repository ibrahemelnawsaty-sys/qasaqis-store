{{-- إيميل استعادة كلمة المرور — يرث القالب المؤسسي. المتغيّرات: $name · $url · $expire --}}
@extends('emails.layout')

@section('kicker', 'استعادة كلمة المرور')
@section('preheader', 'رابط تعيين كلمة مرور جديدة لحسابك — صالح لمدة محدودة.')

@section('content')
    <p style="font-size:16px;margin:0 0 14px;font-weight:800;">{{ __('account.password.mail.greeting', ['name' => $name]) }}</p>
    <p style="font-size:14.5px;line-height:1.9;color:#6e6280;margin:0 0 6px;">{{ __('account.password.mail.line_1') }}</p>

    @include('emails.partials.button', ['url' => $url, 'label' => __('account.password.mail.action')])

    <p style="font-size:13px;line-height:1.8;color:#8b7fa0;margin:16px 0 0;text-align:center;">{{ __('account.password.mail.expire', ['count' => $expire]) }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0 14px;">
        <tr><td style="border-top:1px solid #efe6da;font-size:0;line-height:0;">&nbsp;</td></tr>
    </table>

    <p style="font-size:13px;line-height:1.8;color:#a99fb6;margin:0 0 14px;">{{ __('account.password.mail.line_2') }}</p>

    <p style="font-size:11.5px;line-height:1.7;color:#b7adc4;margin:0;">
        إن لم يعمل الزر، انسخي هذا الرابط والصقيه في المتصفّح:<br>
        <a href="{{ $url }}" style="color:#6E2FB0;word-break:break-all;">{{ $url }}</a>
    </p>
@endsection
