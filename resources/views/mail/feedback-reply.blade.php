@extends('mail.layout')

@section('preview')
Re: your report on {{ $feedback->sku_name }} — Balloonventory
@endsection

@section('content')
<p style="margin:0 0 20px;font-size:11px;font-weight:600;color:#6D28D9;text-transform:uppercase;letter-spacing:0.08em;">Balloonventory</p>

<p style="margin:0 0 16px;font-size:14px;color:#3F3F46;">Hi {{ $recipientName }},</p>

<p style="margin:0 0 16px;font-size:14px;color:#3F3F46;">Thanks for the feedback you sent us on <strong>{{ $feedback->sku_name }}</strong>. Here's our reply:</p>

<div style="font-size:15px;line-height:1.6;color:#0A0A0A;white-space:pre-wrap;">{{ $replyBody }}</div>

<p style="margin:24px 0 0;font-size:14px;color:#3F3F46;">— Tallie at Balloonventory</p>

{{-- Original report --}}
<div style="margin:32px 0 0;border-top:1px solid #E4E4E7;padding-top:20px;">
    <p style="margin:0 0 8px;font-size:11px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Your original report</p>
    <p style="margin:0 0 12px;font-size:12px;color:#A1A1AA;">{{ $feedback->created_at->format('M j, Y \a\t g:i a') }}</p>
    <p style="margin:0 0 6px;font-size:13px;color:#71717A;"><strong>{{ $feedback->sku_name }}</strong> — {{ $fieldLabel }}</p>
    @if ($feedback->suggested_value)
        <p style="margin:0 0 6px;font-size:13px;color:#71717A;">
            @if ($feedback->current_value)<span style="text-decoration:line-through;">{{ $feedback->current_value }}</span> → @endif{{ $feedback->suggested_value }}
        </p>
    @endif
    @if ($feedback->note)
        <div style="font-size:13px;line-height:1.6;color:#71717A;white-space:pre-wrap;">{{ $feedback->note }}</div>
    @endif
</div>
@endsection
