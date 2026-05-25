<?php

namespace App\Models;

use App\Support\Gtin;
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
        'price_code_id',
        'is_active',
        'discontinued_at',
        'product_version',
        'owned_by_business_id',
        'url',
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
            if ($model->isDirty(['brand_id', 'color_id', 'default_count_per_bag', 'balloon_size_id'])) {
                $model->computed_name = $model->generateComputedName();
            }

            if ($model->isDirty(['upc', 'brand_id'])) {
                $model->gs1_prefix = self::deriveGs1Prefix($model->upc, $model->brand_id);
            }

            if ($model->isDirty('is_active')) {
                $model->discontinued_at = $model->is_active
                    ? null
                    : ($model->discontinued_at ?: now());
            }
        });
    }

    public function setUpcAttribute(?string $value): void
    {
        $this->attributes['upc'] = $this->normalizeBarcode($value);
    }

    public function setEanAttribute(?string $value): void
    {
        $this->attributes['ean'] = $this->normalizeBarcode($value);
    }

    /**
     * Normalize a UPC/EAN input on save: trim, treat "na"/"n/a"/empty as null,
     * and strip non-digit separators (spaces, dashes) from anything else so
     * the stored value is canonical digit-only. Validation of length and
     * check digit is the controller's job — this method only cleans format.
     */
    private function normalizeBarcode(?string $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['', 'na', 'n/a'], true)) {
            return null;
        }

        $digits = Gtin::digitsOnly($value);

        return $digits === '' ? $value : $digits;
    }

    private static function deriveGs1Prefix(?string $upc, ?string $brandId): ?string
    {
        if (! $upc || ! $brandId) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $upc);

        if ($digits === '') {
            return null;
        }

        $prefixes = BrandGs1Prefix::where('brand_id', $brandId)
            ->orderByRaw('LENGTH(prefix) DESC')
            ->pluck('prefix');

        foreach ($prefixes as $prefix) {
            if (str_starts_with($digits, $prefix)) {
                return $prefix;
            }
        }

        return null;
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

    public function linkIdentical(self $other): void
    {
        if ($other->id === $this->id) {
            throw new \InvalidArgumentException('A SKU cannot be marked as identical to itself.');
        }

        $this->identicalSkus()->syncWithoutDetaching([$other->id]);
        $other->identicalSkus()->syncWithoutDetaching([$this->id]);
    }

    public function unlinkIdentical(self $other): void
    {
        $this->identicalSkus()->detach($other->id);
        $other->identicalSkus()->detach($this->id);
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
        $this->loadMissing(['balloonSize.shape', 'color', 'brand']);

        $parts = array_filter([
            $this->balloonSize?->name,
            $this->color?->name,
            $this->brand?->abbreviation,
            $this->balloonSize?->shape?->name,
            $this->default_count_per_bag ? $this->default_count_per_bag.'ct' : null,
        ]);

        return implode(' ', $parts);
    }
}
