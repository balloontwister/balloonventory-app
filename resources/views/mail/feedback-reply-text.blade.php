Hi {{ $recipientName }},

Thanks for the feedback you sent us on {{ $feedback->sku_name }}. Here's our reply:

{{ $replyBody }}

— Tallie at Balloonventory

---

Your original report ({{ $feedback->created_at->format('M j, Y') }}):

{{ $feedback->sku_name }} — {{ $fieldLabel }}
@if ($feedback->suggested_value)
@if ($feedback->current_value){{ $feedback->current_value }} -> @endif{{ $feedback->suggested_value }}
@endif
@if ($feedback->note)

{{ $feedback->note }}
@endif
