SUPPORT REQUEST
===============

From: {{ $user->name }} <{{ $user->email }}>
Member since: {{ $user->created_at->format('M j, Y') }}
@if($user->memberships && $user->memberships->count())
Business: {{ $user->memberships->map(fn($m) => $m->business->name.' ('.$m->role.')')->implode(', ') }}
@endif

Subject: {{ $subject }}

---

{{ $body }}

---
Reply to this email to respond directly to {{ $user->name }}.
