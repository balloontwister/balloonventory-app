@extends('mail.layout')

@section('content')
{{-- Header label --}}
<p style="margin:0 0 20px;font-size:11px;font-weight:600;color:#6D28D9;text-transform:uppercase;letter-spacing:0.08em;">Support Request</p>

{{-- User context block --}}
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;background:#F4F4F5;border-radius:10px;padding:16px;">
    <tr>
        <td style="padding:12px 16px;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td style="padding:3px 0;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;width:90px;">Name</td>
                    <td style="padding:3px 0;font-size:14px;color:#0A0A0A;">{{ $user->name }}</td>
                </tr>
                <tr>
                    <td style="padding:3px 0;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Email</td>
                    <td style="padding:3px 0;font-size:14px;color:#0A0A0A;">{{ $user->email }}</td>
                </tr>
                @if($user->memberships && $user->memberships->count())
                <tr>
                    <td style="padding:3px 0;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Business</td>
                    <td style="padding:3px 0;font-size:14px;color:#0A0A0A;">
                        {{ $user->memberships->map(fn($m) => $m->business->name.' ('.$m->role.')')->implode(', ') }}
                    </td>
                </tr>
                @endif
                <tr>
                    <td style="padding:3px 0;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Member since</td>
                    <td style="padding:3px 0;font-size:14px;color:#0A0A0A;">{{ $user->created_at->format('M j, Y') }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Subject --}}
<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Subject</p>
<p style="margin:0 0 20px;font-size:16px;font-weight:600;color:#0A0A0A;">{{ $userSubject }}</p>

{{-- Message --}}
<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:#A1A1AA;text-transform:uppercase;letter-spacing:0.06em;">Message</p>
<div style="font-size:15px;line-height:1.6;color:#0A0A0A;white-space:pre-wrap;">{{ $body }}</div>

{{-- Reply nudge --}}
<p style="margin:24px 0 0;font-size:13px;color:#A1A1AA;border-top:1px solid #E4E4E7;padding-top:16px;">
    Hit Reply to respond directly to {{ $user->name }} at {{ $user->email }}.
</p>
@endsection
