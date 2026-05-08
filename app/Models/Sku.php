<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sku extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'brand_id',
        'size',
        'color_name',
        'color_hex',
        'finish',
        'default_count_per_bag',
        'manufacturer_sku',
        'price_code',
        'image_url',
        'owned_by_business_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function owningBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'owned_by_business_id');
    }

    public function upcs(): HasMany
    {
        return $this->hasMany(Upc::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function businessOverrides(): HasMany
    {
        return $this->hasMany(BusinessSkuOverride::class);
    }

    public function isShared(): bool
    {
        return $this->owned_by_business_id === null;
    }
}
