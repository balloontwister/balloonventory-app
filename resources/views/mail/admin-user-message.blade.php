@extends('mail.layout')

@section('preview')
{{ $bodyText }}
@endsection

@section('content')
<p style="margin:0 0 20px;font-size:11px;font-weight:600;color:#6D28D9;text-transform:uppercase;letter-spacing:0.08em;">Balloonventory</p>

<p style="margin:0 0 16px;font-size:14px;color:#3F3F46;">Hi {{ $recipientName }},</p>

<div style="font-size:15px;line-height:1.6;color:#0A0A0A;white-space:pre-wrap;">{{ $bodyText }}</div>

<p style="margin:24px 0 0;font-size:14px;color:#3F3F46;">— Tallie at Balloonventory</p>
@endsection
