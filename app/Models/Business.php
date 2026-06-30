<?php

namespace App\Models;

use App\Enums\BusinessFrozenReason;
use App\Enums\BusinessPlan;
use App\Scopes\BusinessScope;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'created_by_user_id',
        'plan',
        'logo_path',
        'business_type',
        'onboarding_answers',
        'onboarding_completed_at',
        'phone',
        'address_line1',
        'address_line2',
        'city',
        'state_region',
        'postal_code',
        'country',
        'website_url',
        'website_url_2',
        'contact_email',
    ];

    protected function casts(): array
    {
        return [
            'plan' => BusinessPlan::class,
            'onboarding_answers' => 'array',
            'onboarding_completed_at' => 'datetime',
            'frozen_at' => 'datetime',
            'frozen_reason' => BusinessFrozenReason::class,
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
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * The user who originally created this business. May be null for legacy rows
     * that predate the column and had no resolvable owner during backfill.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function businessInvitations(): HasMany
    {
        return $this->hasMany(BusinessInvitation::class);
    }

    public function lists(): HasMany
    {
        return $this->hasMany(BalloonList::class);
    }

    public function favoritesList(): ?BalloonList
    {
        return $this->lists()->where('is_business_favorites', true)->first();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function defaultLocation(): ?Location
    {
        return $this->locations()->where('is_default', true)->first();
    }

    public function bins(): HasMany
    {
        return $this->hasMany(Bin::class);
    }

    public function defaultBin(): ?Bin
    {
        return $this->bins()->where('is_default', true)->first();
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(Job::class);
    }

    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'business_distributors')
            ->withPivot(['sort_order', 'is_enabled'])
            ->wherePivot('is_enabled', true)
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    public function isFrozen(): bool
    {
        return $this->frozen_at !== null;
    }

    /**
     * Freeze the business with a reason, unless it is already frozen (the first
     * reason wins — e.g. an admin suspension is not silently relabelled).
     */
    public function freeze(BusinessFrozenReason $reason): void
    {
        if ($this->isFrozen()) {
            return;
        }

        $this->forceFill([
            'frozen_at' => now(),
            'frozen_reason' => $reason,
        ])->save();
    }

    /**
     * Lift the freeze and clear the reason.
     */
    public function thaw(): void
    {
        $this->forceFill([
            'frozen_at' => null,
            'frozen_reason' => null,
        ])->save();
    }

    /**
     * The primary owner of this business: the earliest-joined member with the
     * 'owner' role, falling back to the earliest member of any role. Bypasses
     * the tenant scope so it resolves regardless of the caller's current
     * business context, and uses portable ordering (no MySQL-only FIELD()).
     */
    public function owner(): ?User
    {
        return $this->memberships()
            ->withoutGlobalScope(BusinessScope::class)
            ->whereNull('deleted_at')
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('joined_at', 'asc')
            ->with('user')
            ->first()
            ?->user;
    }
}
