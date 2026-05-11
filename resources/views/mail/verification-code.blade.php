@extends('mail.layout')

@section('preview', 'Your Balloonventory verification code: ' . $code)

@section('content')
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Hi {{ $userName }},</p>
<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#0A0A0A;">Thanks for signing up for Balloonventory. Enter this code on the verification page to complete your registration:</p>

<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
    <tr>
        <td align="center">
            <div style="display:inline-block;background-color:#F4F4F5;border:1px solid #E4E4E7;border-radius:10px;padding:16px 32px;">
                <span style="font-family:'JetBrains Mono',ui-monospace,'Courier New',monospace;font-size:36px;font-weight:700;letter-spacing:12px;color:#0A0A0A;">{{ $code }}</span>
            </div>
        </td>
    </tr>
</table>

<p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#52525B;">This code expires in <strong>15 minutes</strong>.</p>
<p style="margin:0;font-size:13px;line-height:1.6;color:#A1A1AA;">If you didn't create a Balloonventory account, you can safely ignore this email.</p>
@endsection
