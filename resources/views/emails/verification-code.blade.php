{{-- إيميل كود تأكيد البريد — يرث القالب المؤسسي. المتغيّرات: $code · $minutes --}}
@extends('emails.layout')

@section('kicker', 'تأكيد بريدك')
@section('preheader', 'كود تأكيد بريدك الإلكتروني في قصص أطفال.')

@section('content')
    <p style="font-size:16px;margin:0 0 12px;font-weight:800;">{{ __('verification.email.greeting') }}</p>
    <p style="font-size:14.5px;line-height:1.9;color:#6e6280;margin:0 0 20px;">{{ __('verification.email.intro') }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px;">
        <tr><td align="center">
            <div style="display:inline-block;background:#f0e6fa;border:2px dashed #6E2FB0;border-radius:14px;padding:16px 34px;">
                <div style="font-size:32px;font-weight:800;letter-spacing:8px;color:#54228A;direction:ltr;font-family:'Courier New',Consolas,monospace;">{{ $code }}</div>
            </div>
        </td></tr>
    </table>

    <p style="font-size:13px;line-height:1.8;color:#8b7fa0;margin:0 0 14px;text-align:center;">{{ __('verification.email.expiry', ['minutes' => $minutes]) }}</p>
    <p style="font-size:12.5px;line-height:1.7;color:#a99fb6;margin:0;">{{ __('verification.email.ignore') }}</p>
@endsection
