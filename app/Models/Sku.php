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
        'description',
        'brand_id',
        'material_id',
        'balloon_size_id',
        'shape_id',
        'texture_id',
        'color_id',
        'is_printed',
        'default_count_per_bag',
        'warehouse_sku',
        'upc',
        'ean',
        'asin',
        'mfg_no',
        'packaging_id',
        'single_image_file_path',
        'cluster_image_file_path',
        'computed_name',
        'price_code_id',
        'gs1_prefix',
        'is_active',
        'discontinued_at',
        'product_version',
        'owned_by_business_id',
    ];

    protected $casts = [
        'is_printed' => 'boolean',
        'is_active' => 'boolean',
        'default_count_per_bag' => 'integer',
        'discontinued_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });

        static::saving(function (self $model) {
            if ($model->isDirty(['brand_id', 'color_id', 'shape_id', 'default_count_per_bag', 'balloon_size_id'])) {
                $model->computed_name = $model->generateComputedName();
            }
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function balloonSize(): BelongsTo
    {
        return $this->belongsTo(BalloonSize::class);
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

    public function packagingType(): BelongsTo
    {
        return $this->belongsTo(PackagingType::class, 'packaging_id');
    }

    public function priceCode(): BelongsTo
    {
        return $this->belongsTo(PriceCode::class);
    }

    public function themes(): BelongsToMany
    {
        return $this->belongsToMany(Theme::class, 'sku_themes');
    }

    public function printColors(): BelongsToMany
    {
        return $this->belongsToMany(PrintColor::class, 'sku_print_colors');
    }

    public function printSides(): BelongsToMany
    {
        return $this->belongsToMany(PrintSide::class, 'sku_print_sides');
    }

    public function identicalSkus(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'identical_skus',
            'sku_id',
            'identical_sku_id',
        );
    }

    public function owningBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'owned_by_business_id');
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

    private function generateComputedName(): string
    {
        $parts = [];

        // Load relations if not already loaded so we can use their names.
        if ($this->relationLoaded('balloonSize') && $this->balloonSize) {
            $parts[] = $this->balloonSize->name;
        }
        if ($this->relationLoaded('color') && $this->color) {
            $parts[] = $this->color->name;
        }
        if ($this->relationLoaded('brand') && $this->brand) {
            $parts[] = $this->brand->abbreviation;
        }
        if ($this->relationLoaded('shape') && $this->shape) {
            $parts[] = $this->shape->name;
        }
        if ($this->default_count_per_bag) {
            $parts[] = $this->default_count_per_bag.'ct';
        }

        return implode(' ', $parts);
    }
}
