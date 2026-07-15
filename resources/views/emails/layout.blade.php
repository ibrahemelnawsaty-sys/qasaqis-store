<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('common.brand') }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f1ea;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#372a46;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ea;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl"
                    style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(84,34,138,.12);">
                    <tr>
                        <td style="background:linear-gradient(135deg,#6E2FB0,#EC4E96);padding:24px 28px;text-align:center;">
                            <div style="color:#ffffff;font-size:22px;font-weight:800;">{{ __('common.brand') }} 🌟</div>
                            @hasSection('kicker')
                                <div style="color:#ffffff;opacity:.9;font-size:13px;margin-top:6px;">@yield('kicker')</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#fbf6ee;padding:16px 28px;text-align:center;color:#a99fb6;font-size:12px;">
                            © {{ date('Y') }} {{ __('common.brand') }} — {{ __('common.domain') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
