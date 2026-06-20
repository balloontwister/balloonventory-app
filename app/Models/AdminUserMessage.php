<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A one-off email an admin composed and sent to a specific user. The body is
 * emailed to the recipient and kept here as the record of what was said.
 * Mirrors {@see SkuFeedbackReply}.
 */
class AdminUserMessage extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'sender_user_id', 'subject', 'body', 'template_key'];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id')->withTrashed();
    }
}
