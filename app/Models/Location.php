<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Location extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'is_default',
        'sort_order',
        'position_locked',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
            'position_locked' => 'boolean',
        ];
    }

    /**
     * The canonical display order for locations: user-defined position
     * (sort_order, set by drag-reorder), then name. Single source of truth so
     * the wall and Manage storage stay consistent.
     */
    public function scopeOrderedForDisplay(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
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
            if ($model->is_default) {
                throw new \RuntimeException('The Default location cannot be deleted.');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }
}
