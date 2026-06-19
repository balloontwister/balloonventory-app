<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's reply to a user's item-feedback report. The body is emailed to the
 * reporter and kept here as the record of what was said. Mirrors
 * {@see SupportTicketReply}.
 */
class SkuFeedbackReply extends Model
{
    use HasUuids;

    protected $fillable = ['sku_feedback_id', 'user_id', 'body'];

    public function feedback(): BelongsTo
    {
        return $this->belongsTo(SkuFeedback::class, 'sku_feedback_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
