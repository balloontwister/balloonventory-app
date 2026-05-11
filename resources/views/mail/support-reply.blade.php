@extends('mail.layout')

@section('preview')
Re: {{ $ticket->subject }} — Balloonventory Support
@endsection

@section('content')
<p style="margin:0 0 20px;font-size:11px;font-weight:600;color:#6D28D9;text-transform:uppercase;letter-spacing:0.08em;">Balloonventory Support</p>

<p style="margin:0 0 16px;font-size:14px;color:#3F3F46;">Hi {{ $ticket->user_name }},</p>

<div style="font-size:15px;line-height:1.6;color:#0A0A0A;white-space:pre-wrap;">{{ $replyBody }}</div>

<p style="margin:24px 0 0;font-size:14px;color:#3F3F46;">— Tallie at Balloonventory</p>

{{-- Original message --}}
<div style="margin:32px 0 0;border-top:1px solid #E4E4E7;padding-top:20px;">
    <p style="margin:0 0 8px;font-size:11px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Your original message</p>
    <p style="margin:0 0 12px;font-size:12px;color:#A1A1AA;">{{ $ticket->created_at->format('M j, Y \a\t g:i a') }}</p>
    <div style="font-size:13px;line-height:1.6;color:#71717A;white-space:pre-wrap;">{{ $ticket->body }}</div>
</div>
@endsection
