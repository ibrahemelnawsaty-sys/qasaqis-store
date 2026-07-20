{{--
| ============================================================================
|  emails/layout.blade.php — القالب الأساسي لرسائل «قصاقيص أطفال»
|  العقد المحفوظ:  @yield('content') [إلزامي] · @section('kicker') · @section('preheader')
|  متغيّرات اختيارية:  $unsubscribeUrl · $reasonLine
|  ألوان: بنفسجي #6E2FB0/#54228A · وردي #EC4E96 · فيروزي #12B3A6 · ذهبي #FFC23C · حبري #372A46 · خلفية #F5F1EA
|  الوضع الداكن يمسّ الخلفية الخارجية فقط؛ البطاقة تبقى فاتحة (أبناء القوالب يستخدمون ألوان inline فاتحة السطح).
| ============================================================================
--}}
@php
    $brand   = __('common.brand');
    $tagline = __('common.tagline');
    $domain  = __('common.domain');
    $siteUrl = 'https://' . $domain;
    $fromEmail = config('mail.from.address', 'hello@' . $domain);

    // المصدر نفسه الذي تستعمله الواجهة والفوتر: المصفوفة المشتركة $storeSettings
    // (يبنيها AppServiceProvider من جدول الإعدادات وتُشارَك لكل العروض عبر View::share،
    // فتشمل عروض البريد). هكذا يظهر رقم الواتساب والسوشيال الحقيقيان لا قيَم config
    // الافتراضية. حارس ?? [] للتصيير خارج طلب (يتراجع لـ config للواتساب فقط).
    $storeSettings = $storeSettings ?? [];
    $waDigits = preg_replace('/\D+/', '', (string) ($storeSettings['whatsapp_number'] ?? config('services.store.whatsapp', '')));
    $waLink   = $waDigits ? 'https://wa.me/' . $waDigits : null;

    // روابط السوشيال بترتيب العرض؛ لا يظهر إلا ما ضُبط فعلًا في الإعدادات.
    $socials = array_filter([
        'instagram' => (string) ($storeSettings['social_instagram'] ?? ''),
        'facebook'  => (string) ($storeSettings['social_facebook'] ?? ''),
        'tiktok'    => (string) ($storeSettings['social_tiktok'] ?? ''),
        'youtube'   => (string) ($storeSettings['social_youtube'] ?? ''),
        'twitter'   => (string) ($storeSettings['social_twitter'] ?? ''),
        'snapchat'  => (string) ($storeSettings['social_snapchat'] ?? ''),
        'telegram'  => (string) ($storeSettings['social_telegram'] ?? ''),
    ], static fn (string $v): bool => trim($v) !== '');

    $reasonLine = $reasonLine ?? 'تصلك هذه الرسالة لأنك تعاملت مع متجر «' . $brand . '» أو سجّلت بريدك لدينا.';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office" lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no,date=no,address=no,email=no">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $brand }}</title>
    <!--[if mso]>
    <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
    <style>table,td,div,p,a{font-family:Tahoma,Arial,sans-serif !important;}</style>
    <![endif]-->
    <style>
        /* خطوط الموقع نفسها (مستضافة محليًا) — تُحمَّل في العملاء الداعمين (Apple Mail/iOS)
           وإلا يتراجع للنظام. تُضفي فخامة الهوية على البريد كما في الواجهة. */
        @font-face{font-family:'Tajawal';font-style:normal;font-weight:400;font-display:swap;src:url('{{ $siteUrl }}/fonts/tajawal-400-ar.woff2') format('woff2');}
        @font-face{font-family:'Tajawal';font-style:normal;font-weight:500;font-display:swap;src:url('{{ $siteUrl }}/fonts/tajawal-500-ar.woff2') format('woff2');}
        @font-face{font-family:'Tajawal';font-style:normal;font-weight:700;font-display:swap;src:url('{{ $siteUrl }}/fonts/tajawal-700-ar.woff2') format('woff2');}
        @font-face{font-family:'Baloo Bhaijaan 2';font-style:normal;font-weight:800;font-display:swap;src:url('{{ $siteUrl }}/fonts/baloo-800-ar.woff2') format('woff2');}
        body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;}
        table,td{mso-table-lspace:0pt;mso-table-rspace:0pt;}
        img{-ms-interpolation-mode:bicubic;border:0;outline:none;text-decoration:none;}
        body{margin:0 !important;padding:0 !important;width:100% !important;}
        a{text-decoration:none;}
        a[x-apple-data-detectors]{color:inherit !important;text-decoration:none !important;}
        @media only screen and (max-width:600px){
            .qa-card{width:100% !important;border-radius:0 !important;}
            .qa-pad,.qa-pad-lg{padding-left:22px !important;padding-right:22px !important;}
            .qa-wordmark{font-size:20px !important;}
            .qa-btn a{display:block !important;width:auto !important;text-align:center !important;}
            .qa-social td{padding:0 6px !important;}
            .qa-hide-sm{display:none !important;}
        }
        /* الوضع الداكن: الخلفية الخارجية فقط — البطاقة تبقى فاتحة عمدًا */
        @media (prefers-color-scheme:dark){
            .qa-bg{background:#171021 !important;}
            .qa-outer-foot{color:#9C90B4 !important;}
        }
        [data-ogsc] .qa-bg{background:#171021 !important;}
        /* محتوى الحملات (RichEditor + معقَّم بلا أنماط سطرية): يأخذ شكله من هنا */
        .qa-rich h2{font-family:'Baloo Bhaijaan 2','Tajawal',Tahoma,sans-serif;font-size:20px;font-weight:800;color:#54228A;line-height:1.5;margin:0 0 12px;}
        .qa-rich h3{font-family:'Baloo Bhaijaan 2','Tajawal',Tahoma,sans-serif;font-size:16px;font-weight:800;color:#6E2FB0;line-height:1.6;margin:18px 0 8px;}
        .qa-rich p{margin:0 0 14px;}
        .qa-rich a{color:#6E2FB0 !important;font-weight:700;text-decoration:underline;}
        .qa-rich ul,.qa-rich ol{margin:0 0 14px;padding-inline-start:22px;}
        .qa-rich li{margin:0 0 6px;}
        .qa-rich blockquote{margin:0 0 16px;padding:12px 16px;background:#f0e6fa;border-inline-start:4px solid #6E2FB0;border-radius:8px;color:#4a3d5c;}
        .qa-rich strong{color:#372a46;}
    </style>
</head>
<body class="qa-bg" style="margin:0;padding:0;background:#F5F1EA;font-family:'Tajawal','Segoe UI',Tahoma,Arial,'Helvetica Neue',Helvetica,sans-serif;color:#372A46;">

    {{-- preheader: نص المعاينة في صندوق الوارد. الفاصل ثابت موثوق ⇒ {!! !!} كي تبقى الرموز غير مرئية --}}
    <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#F5F1EA;opacity:0;">
        @hasSection('preheader')@yield('preheader')@else{{ $tagline }}@endif
        {!! str_repeat('&#8199;&#65279;', 30) !!}
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="qa-bg" style="background:#F5F1EA;">
      <tr><td align="center" style="padding:28px 12px;">
        <!--[if mso]><table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" dir="rtl" class="qa-card" style="width:600px;max-width:600px;background:#FFFFFF;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(84,34,138,.14);">

          {{-- الترويسة --}}
          <tr>
            <td bgcolor="#6E2FB0" class="qa-pad-lg" style="background:#6E2FB0;background:linear-gradient(120deg,#54228A 0%,#6E2FB0 45%,#EC4E96 100%);padding:26px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl"><tr>
                <td width="52" valign="middle" style="width:52px;padding-inline-end:14px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td width="52" height="52" align="center" valign="middle" bgcolor="#fff6ea" style="width:52px;height:52px;background:#fff6ea;border-radius:14px;">
                      <img src="{{ $siteUrl }}/images/logo.png" width="52" height="52" alt="{{ $brand }}" style="display:block;width:52px;height:52px;border-radius:14px;border:0;">
                    </td>
                  </tr></table>
                </td>
                <td valign="middle" align="right" style="text-align:right;">
                  <div class="qa-wordmark" style="font-family:'Baloo Bhaijaan 2','Tajawal',Tahoma,sans-serif;color:#FFFFFF;font-size:23px;font-weight:800;line-height:1.2;">{{ $brand }}</div>
                  <div class="qa-hide-sm" style="color:#FFFFFF;opacity:.88;font-size:12.5px;line-height:1.4;margin-top:3px;">{{ $tagline }}</div>
                </td>
              </tr></table>
              @hasSection('kicker')
                <div style="margin-top:16px;"><span style="display:inline-block;background:rgba(255,255,255,.18);color:#FFFFFF;font-size:12.5px;font-weight:700;line-height:1;padding:8px 14px;border-radius:999px;">@yield('kicker')</span></div>
              @endif
            </td>
          </tr>

          {{-- شريط ذهبي→فيروزي --}}
          <tr><td style="height:4px;line-height:4px;font-size:0;background:#FFC23C;background:linear-gradient(90deg,#FFC23C,#12B3A6);">&nbsp;</td></tr>

          {{-- المحتوى (العقد المحفوظ) --}}
          <tr><td class="qa-body qa-pad" style="padding:32px;color:#372A46;font-size:15px;line-height:1.8;">@yield('content')</td></tr>

          {{-- التذييل الغني --}}
          <tr>
            <td class="qa-footer qa-pad" bgcolor="#FBF6EE" style="background:#FBF6EE;padding:26px 32px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl"><tr>
                <td align="center" style="text-align:center;">
                  <div style="font-family:'Baloo Bhaijaan 2','Tajawal',Tahoma,sans-serif;font-size:16px;font-weight:800;color:#54228A;">{{ $brand }}</div>
                  <div style="font-size:12.5px;color:#8B7FA0;margin-top:3px;">{{ $tagline }}</div>
                </td>
              </tr></table>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl" style="margin-top:14px;"><tr>
                <td align="center" style="text-align:center;font-size:13px;color:#6E6280;line-height:1.9;">
                  <a href="mailto:{{ $fromEmail }}" style="color:#6E2FB0;text-decoration:none;font-weight:700;direction:ltr;display:inline-block;">{{ $fromEmail }}</a>
                </td>
              </tr></table>

              {{-- أيقونات السوشيال الحقيقية (شعارات العلامات) من إعدادات الموقع — يظهر ما ضُبط
                   فقط. صور PNG مستضافة (لا SVG) لأن Gmail يزيل SVG؛ واتساب أوّلًا ثم المنصّات. --}}
              @if ($waLink || ! empty($socials))
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl" style="margin:18px 0 0;"><tr>
                  <td align="center" style="text-align:center;">
                    @if ($waLink)
                      <a href="{{ $waLink }}" style="display:inline-block;margin:0 5px;text-decoration:none;"><img src="{{ $siteUrl }}/images/email/social/whatsapp.png" width="30" height="30" alt="واتساب" style="display:block;width:30px;height:30px;border:0;"></a>
                    @endif
                    @foreach ($socials as $qaKey => $qaUrl)
                      <a href="{{ $qaUrl }}" style="display:inline-block;margin:0 5px;text-decoration:none;"><img src="{{ $siteUrl }}/images/email/social/{{ $qaKey }}.png" width="30" height="30" alt="{{ __('footer.social_' . $qaKey) }}" style="display:block;width:30px;height:30px;border:0;"></a>
                    @endforeach
                  </td>
                </tr></table>
              @endif

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;"><tr><td style="border-top:1px solid #EFE6DA;font-size:0;line-height:0;">&nbsp;</td></tr></table>

              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" dir="rtl"><tr>
                <td align="center" class="qa-outer-foot" style="text-align:center;font-size:11.5px;line-height:1.85;color:#A99FB6;">
                  <div style="direction:ltr;">{{ $brand }} · {{ $domain }}</div>
                  {{-- ملاحظة للمالك: أضِف هنا عنوانًا بريديًا حقيقيًا قبل أي إرسال تسويقي (متطلّب CAN-SPAM/قواعد Gmail للبريد الجماعي) --}}
                  <div style="margin-top:6px;">{{ $reasonLine }}</div>
                  <div style="margin-top:8px;">
                    <a href="{{ $siteUrl }}" style="color:#8B7FA0;text-decoration:underline;">زيارة المتجر</a>
                    @if (! empty($unsubscribeUrl))
                      <span style="color:#C9BFD6;">&nbsp;·&nbsp;</span>
                      <a href="{{ $unsubscribeUrl }}" style="color:#8B7FA0;text-decoration:underline;">إلغاء الاشتراك</a>
                    @endif
                  </div>
                  <div style="margin-top:10px;color:#B7ADC4;">{{ __('footer.rights', ['year' => date('Y')]) }}</div>
                </td>
              </tr></table>
            </td>
          </tr>
        </table>
        <!--[if mso]></td></tr></table><![endif]-->
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;"><tr><td style="height:20px;line-height:20px;font-size:0;">&nbsp;</td></tr></table>
      </td></tr>
    </table>
</body>
</html>
