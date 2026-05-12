<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'size_id',
        'shape_id',
        'texture_id',
        'color_id',
        'material_id',
        'is_printed',
        'default_count_per_bag',
        'manufacturer_sku',
        'price_code',
        'image_url',
        'owned_by_business_id',
    ];

    protected $casts = [
        'is_printed' => 'boolean',
        'default_count_per_bag' => 'integer',
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

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function shape(): BelongsTo
    {
        return $this->belongsTo(Shape::class);
    }

    public function texture(): BelongsTo
    {
        return $this->belongsTo(Texture::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'sku_themes');
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
