{{-- صفحة إلغاء الاشتراك العامة (متصفّح). مستقلّة وبسيطة، بهوية «قصاقيص أطفال». --}}
@php
    $brand = __('common.brand');
    $domain = __('common.domain');
    $siteUrl = 'https://' . $domain;
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $confirmed ? 'تم إلغاء الاشتراك' : 'إلغاء الاشتراك' }} — {{ $brand }}</title>
    <style>
        body{margin:0;font-family:'Segoe UI',Tahoma,Arial,sans-serif;background:#F5F1EA;color:#372A46;
             display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
        .card{background:#fff;border-radius:18px;box-shadow:0 10px 30px rgba(84,34,138,.14);
              max-width:460px;width:100%;padding:38px 30px;text-align:center;}
        .badge{width:64px;height:64px;border-radius:18px;margin:0 auto 18px;display:flex;
               align-items:center;justify-content:center;font-size:30px;}
        .ok{background:#e2f6f4;}
        .ask{background:#f0e6fa;}
        h1{font-size:21px;font-weight:800;color:#54228A;margin:0 0 10px;}
        p{font-size:14.5px;line-height:1.9;color:#6e6280;margin:0 0 18px;}
        .email{direction:ltr;display:inline-block;background:#FBF6EE;border-radius:8px;
               padding:4px 12px;font-weight:700;color:#6E2FB0;}
        .btn{display:inline-block;border:0;cursor:pointer;background:#6E2FB0;color:#fff;font-weight:800;
             font-size:15px;padding:13px 34px;border-radius:999px;text-decoration:none;font-family:inherit;}
        .btn.danger{background:#EC4E96;}
        .link{display:inline-block;margin-top:18px;color:#8B7FA0;font-size:13px;text-decoration:underline;}
    </style>
</head>
<body>
    <div class="card">
        @if ($confirmed)
            <div class="badge ok">✅</div>
            <h1>تم إلغاء اشتراكك</h1>
            <p>لن تصلك بعد الآن أي رسائل تسويقية أو عروض على البريد:<br><span class="email">{{ $email }}</span></p>
            <p style="font-size:12.5px;color:#a99fb6;">ستظل تصلك رسائل الطلبات المهمّة (تأكيد الطلب والشحن) لأنها ضرورية لخدمتك.</p>
            <a href="{{ $siteUrl }}" class="btn">العودة إلى المتجر</a>
        @else
            <div class="badge ask">📭</div>
            <h1>إلغاء الاشتراك من العروض</h1>
            <p>هل تريد إيقاف الرسائل التسويقية والعروض المرسلة إلى:<br><span class="email">{{ $email }}</span>؟</p>
            <form method="POST" action="{{ route('email.unsubscribe.store', $token) }}">
                @csrf
                <button type="submit" class="btn danger">نعم، ألغِ اشتراكي</button>
            </form>
            <a href="{{ $siteUrl }}" class="link">تراجعتُ — العودة إلى المتجر</a>
        @endif
    </div>
</body>
</html>
