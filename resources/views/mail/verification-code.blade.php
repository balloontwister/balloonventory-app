@component('mail::message')
# Verify your email address

Hi {{ $userName }}, thanks for signing up for Balloonventory.

Enter this code on the verification page to complete your registration:

@component('mail::panel')
<div style="text-align: center; font-size: 36px; font-weight: 700; letter-spacing: 12px; padding: 8px 0;">{{ $code }}</div>
@endcomponent

This code expires in **15 minutes**. If you didn't create a Balloonventory account, you can safely ignore this email.

Thanks,<br>
Tallie at Balloonventory
@endcomponent
