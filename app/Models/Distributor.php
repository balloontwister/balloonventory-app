<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Distributor extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'contact_email',
        'contact_phone',
        'contact_url',
        'shipping_minimum',
        'free_shipping_threshold',
        'shipping_policy',
        'hours',
        'notes',
        'platform_type',
        'base_url',
        'sitemap_url',
        'api_key',
        'config',
        'is_active',
        'sort_order',
        'last_synced_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'array',
        'shipping_minimum' => 'decimal:2',
        'free_shipping_threshold' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });

        // Soft-deleting a distributor doesn't fire the DB-level FK cascades, so
        // its sku-url, business-preference and catalog-gap rows would otherwise
        // be orphaned (and break the Reorder page). Clean them up here. A force
        // delete is left to the cascading foreign keys.
        static::deleting(function (self $model) {
            if ($model->isForceDeleting()) {
                return;
            }

            $model->skuUrls()->delete();
            $model->businesses()->detach();
            $model->catalogGaps()->delete();
        });
    }

    public function skuUrls(): HasMany
    {
        return $this->hasMany(DistributorSkuUrl::class);
    }

    public function skus(): BelongsToMany
    {
        return $this->belongsToMany(Sku::class, 'distributor_sku_urls')
            ->withPivot(['url', 'price', 'currency', 'in_stock', 'last_checked_at'])
            ->withTimestamps();
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_distributors')
            ->withPivot(['sort_order', 'is_enabled'])
            ->withTimestamps();
    }

    public function catalogGaps(): HasMany
    {
        return $this->hasMany(DistributorCatalogGap::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
