<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ColorFamily extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'material_id',
        'color_hex',
        'hex_color_start',
        'hex_color_end',
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
        return $this->hasMany(ColorFamilyTranslation::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function colors(): HasMany
    {
        return $this->hasMany(Color::class);
    }
}
