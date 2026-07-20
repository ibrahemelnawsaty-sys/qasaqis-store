{{--
| campaign.blade.php — عرض رسالة الحملة. يلفّ محتوى الأدمن المعقَّم داخل القالب
| المؤسسي (ترويسة/تذييل). $bodyHtml ناتج CampaignHtml ⇒ آمن للطباعة بـ{!! !!}.
| $unsubscribeUrl يمرّره القالب الأساسي تلقائيًا إلى التذييل (رابط الإلغاء للحملات فقط).
--}}
@extends('emails.layout')

@section('preheader', $preheader ?? '')

@section('content')
    <div class="qa-rich" style="font-size:15px;line-height:1.9;color:#372a46;">{!! $bodyHtml !!}</div>
@endsection
