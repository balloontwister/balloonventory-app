<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'abbreviation',
        'description',
        'url_1',
        'url_2',
        'logo_url',
        'logo_path',
        'primary_color_hex',
        'secondary_color_hex',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function skus(): HasMany
    {
        return $this->hasMany(Sku::class);
    }

    public function gs1Prefixes(): HasMany
    {
        return $this->hasMany(BrandGs1Prefix::class);
    }
}
