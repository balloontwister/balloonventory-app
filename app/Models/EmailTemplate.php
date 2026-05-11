<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'key',
        'label',
        'trigger_description',
        'subject',
        'body_html',
        'body_text',
        'is_active',
        'last_edited_by_user_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    public static function findByKey(string $key): ?self
    {
        return static::where('key', $key)->first();
    }

    public static function isActive(string $key): bool
    {
        return static::where('key', $key)->where('is_active', true)->exists();
    }
}
