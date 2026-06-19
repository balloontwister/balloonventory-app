<?php

namespace App\Models;

use App\Support\Gtin;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Catalog visibility rule: a SKU is visible to a business when it is shared
     * (no owner) or owned by that business. This is the single source of truth
     * for "may this business act on this SKU" — used by scan and inventory so
     * the two can't drift apart. Soft-deleted rows are excluded by the model's
     * default scope, so callers must not bypass it (e.g. with `exists:skus,id`).
     */
    public function scopeVisibleTo(Builder $query, ?string $businessId): Builder
    {
        return $query->where(function (Builder $q) use ($businessId): void {
            $q->whereNull('owned_by_business_id');

            if ($businessId !== null) {
                $q->orWhere('owned_by_business_id', $businessId);
            }
        });
    }

    /**
     * Free-text search across the fields a person naturally types: the SKU name,
     * computed name, warehouse SKU, and the related brand / size / shape / color /
     * texture names. The term is split into words and EACH word must match at
     * least one field (AND across words, OR across fields), so a query like
     * "Kalisan Blue Link" — whose words live in different columns — still resolves.
     */
    public function scopeMatchesSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        foreach (preg_split('/\s+/', $term) as $word) {
            if ($word === '') {
                continue;
            }

            $like = '%'.$word.'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('skus.name', 'like', $like)
                    ->orWhere('skus.computed_name', 'like', $like)
                    ->orWhere('skus.warehouse_sku', 'like', $like)
                    ->orWhereHas('color', fn (Builder $c) => $c->where('name', 'like', $like))
                    ->orWhereHas('color.texture', fn (Builder $t) => $t->where('name', 'like', $like))
                    ->orWhereHas('brand', fn (Builder $b) => $b
                        ->where('name', 'like', $like)
                        ->orWhere('abbreviation', 'like', $like))
                    ->orWhereHas('balloonSize.size', fn (Builder $s) => $s->where('name', 'like', $like))
                    ->orWhereHas('balloonSize.shape', fn (Builder $s) => $s->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * Instance-level counterpart to scopeVisibleTo() for an already-loaded SKU.
     */
    public function isVisibleTo(?string $businessId): bool
    {
        return $this->owned_by_business_id === null
            || ($businessId !== null && $this->owned_by_business_id === $businessId);
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
