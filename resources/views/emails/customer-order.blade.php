@extends('emails.layout')

@section('kicker', $heading)

@section('content')
    <p style="font-size:16px;margin:0 0 14px;font-weight:800;">{{ $intro }}</p>

    @if (! empty($body))
        <p style="font-size:14.5px;line-height:1.8;color:#6e6280;margin:0 0 18px;">{{ $body }}</p>
    @endif

    @if (! empty($highlight))
        <div style="background:#f0e6fa;border-inline-start:4px solid #6E2FB0;border-radius:10px;padding:14px 18px;margin:0 0 18px;">
            <div style="font-size:14.5px;line-height:1.8;color:#372a46;white-space:pre-line;">{{ $highlight }}</div>
        </div>
    @endif

    <div style="font-size:13px;color:#6e6280;margin:0 0 4px;">
        {{ __('mail.common.order_number') }}:
        <strong style="color:#372a46;direction:ltr;display:inline-block;">{{ $order->order_number }}</strong>
    </div>

    @if (! empty($showSummary))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:12px 0 6px;">
            @foreach ($order->items as $item)
                <tr>
                    <td style="padding:8px 0;font-size:13.5px;color:#372a46;border-top:1px solid #eee;">{{ $item->book_title }} × {{ $item->quantity }}</td>
                    <td style="padding:8px 0;font-size:13.5px;color:#372a46;text-align:end;direction:ltr;border-top:1px solid #eee;">{{ number_format((float) $item->line_total, 0) }} {{ __('common.currency') }}</td>
                </tr>
            @endforeach
            <tr>
                <td style="padding:10px 0 0;border-top:2px solid #6E2FB0;font-weight:800;">{{ __('mail.common.total') }}</td>
                <td style="padding:10px 0 0;border-top:2px solid #6E2FB0;font-weight:800;text-align:end;direction:ltr;">{{ number_format((float) $order->grand_total, 0) }} {{ __('common.currency') }}</td>
            </tr>
        </table>
    @endif

    @if (! empty($ctaUrl) && ! empty($ctaLabel))
        <div style="text-align:center;margin-top:24px;">
            <a href="{{ $ctaUrl }}"
                style="display:inline-block;background:linear-gradient(135deg,#6E2FB0,#EC4E96);color:#ffffff;text-decoration:none;font-weight:800;font-size:14px;padding:12px 26px;border-radius:999px;">{{ $ctaLabel }}</a>
        </div>
    @endif

    @if (! empty($note))
        <p style="font-size:13px;line-height:1.8;color:#6e6280;margin:18px 0 0;">{{ $note }}</p>
    @endif
@endsection
