<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

// Named BalloonList because PHP reserves `list` as a language construct.
class BalloonList extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    protected $table = 'lists';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'name',
        'is_business_favorites',
        'notes',
        'archived_at',
        'visibility',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_business_favorites' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });

        static::deleting(function (self $model) {
            if ($model->is_business_favorites) {
                throw new \RuntimeException('The Favorites list cannot be deleted.');
            }
        });

        static::saving(function (self $model) {
            if ($model->exists && $model->is_business_favorites) {
                if ($model->isDirty('name')) {
                    throw new \RuntimeException('The Favorites list cannot be renamed.');
                }
                if ($model->isDirty('archived_at') && $model->archived_at !== null) {
                    throw new \RuntimeException('The Favorites list cannot be archived.');
                }
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }
}
