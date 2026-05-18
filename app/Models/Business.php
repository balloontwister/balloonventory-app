<?php

namespace App\Models;

use App\Enums\BusinessPlan;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'plan',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'plan' => BusinessPlan::class,
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }

            if (empty($model->plan)) {
                $model->plan = BusinessPlan::Solo;
            }
        });

        // Seed the Favorites list and prevent multiple Favorites rows.
        static::created(function (self $model) {
            // Favorites list is seeded via the BusinessObserver to keep this clean.
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BalloonList::class);
    }

    public function favoritesList(): ?BalloonList
    {
        return $this->lists()->where('is_business_favorites', true)->first();
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }
}
