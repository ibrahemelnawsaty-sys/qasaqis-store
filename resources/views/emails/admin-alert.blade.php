@extends('emails.layout')

@section('kicker', $title)

@section('content')
    <p style="font-size:16px;margin:0 0 14px;font-weight:800;">{{ $title }}</p>

    <div style="font-size:14.5px;line-height:1.9;color:#372a46;white-space:pre-line;margin:0 0 6px;">{{ $body }}</div>

    @if (! empty($url))
        <div style="text-align:center;margin-top:22px;">
            <a href="{{ $url }}"
                style="display:inline-block;background:#6E2FB0;color:#ffffff;text-decoration:none;font-weight:800;font-size:14px;padding:11px 24px;border-radius:999px;">{{ __('mail.admin.view_order') }}</a>
        </div>
    @endif
@endsection
