<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ردّ على استفسارك</title>
</head>
<body style="margin:0;padding:0;background:#f5f1ea;font-family:'Segoe UI',Tahoma,Arial,sans-serif;color:#372a46;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f1ea;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" dir="rtl"
                    style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 24px rgba(84,34,138,.12);">
                    {{-- الترويسة --}}
                    <tr>
                        <td style="background:linear-gradient(135deg,#6E2FB0,#EC4E96);padding:26px 28px;text-align:center;">
                            <div style="color:#ffffff;font-size:22px;font-weight:800;">قصاقيص أطفال 🌟</div>
                            <div style="color:#ffffff;opacity:.9;font-size:13px;margin-top:6px;">ردّ على استفسارك</div>
                        </td>
                    </tr>
                    {{-- المحتوى --}}
                    <tr>
                        <td style="padding:28px;">
                            <p style="font-size:16px;margin:0 0 14px;">أهلًا {{ $inquiry->name }} 👋</p>
                            <p style="font-size:14.5px;line-height:1.8;color:#6e6280;margin:0 0 18px;">
                                شكرًا لتواصلك مع <strong>قصاقيص أطفال</strong>. سعدنا باستفسارك، وإليك ردّنا:
                            </p>

                            {{-- ردّ الفريق --}}
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

                            <p style="font-size:14px;line-height:1.8;color:#6e6280;margin:18px 0 0;">
                                لو عندك أي استفسار آخر، تقدر ترد على هذا البريد أو تتواصل معنا مباشرة.
                                <br>مع تحيات فريق <strong>قصاقيص أطفال</strong> 💛
                            </p>

                            <div style="text-align:center;margin-top:24px;">
                                <a href="https://qasaqis.store"
                                    style="display:inline-block;background:linear-gradient(135deg,#6E2FB0,#EC4E96);color:#ffffff;text-decoration:none;font-weight:800;font-size:14px;padding:12px 26px;border-radius:999px;">
                                    زيارة المتجر
                                </a>
                            </div>
                        </td>
                    </tr>
                    {{-- التذييل --}}
                    <tr>
                        <td style="background:#fbf6ee;padding:16px 28px;text-align:center;color:#a99fb6;font-size:12px;">
                            © {{ date('Y') }} قصاقيص أطفال — qasaqis.store
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
