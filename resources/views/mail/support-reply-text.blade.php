Hi {{ $ticket->user_name }},

{{ $replyBody }}

— Tallie at Balloonventory

---

Your original message ({{ $ticket->created_at->format('M j, Y') }}):

{{ $ticket->body }}
