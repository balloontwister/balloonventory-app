<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Color extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'color_family_id',
        'brand_id',
        'material_id',
        'color_hex',
        'color_code',
        'pms_value',
        'texture_id',
        'single_image_file_path',
        'cluster_image_file_path',
        'sort_order',
        'description',
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

    public function translations(): HasMany
    {
        return $this->hasMany(ColorTranslation::class);
    }

    public function colorFamily(): BelongsTo
    {
        return $this->belongsTo(ColorFamily::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function texture(): BelongsTo
    {
        return $this->belongsTo(Texture::class);
    }

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }
}
