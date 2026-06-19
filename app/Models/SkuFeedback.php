<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A user-submitted "feedback on this item" report — a field-targeted edit or
 * error report raised from the inventory SKU detail page. Deliberately NOT
 * tenant-scoped (no BelongsToBusiness): feedback concerns the shared catalog, so
 * admins review it across all businesses. The business_id column is kept only as
 * context for who reported it.
 */
class SkuFeedback extends Model
{
    use HasFactory;

    protected $table = 'sku_feedback';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'user_id',
        'sku_id',
        'sku_name',
        'field',
        'current_value',
        'suggested_value',
        'note',
        'status',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => FeedbackStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
            if (empty($model->status)) {
                $model->status = FeedbackStatus::Open;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SkuFeedbackReply::class)->orderBy('created_at');
    }
}
