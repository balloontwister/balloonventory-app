<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BusinessSkuOverride extends Model
{
    use HasFactory, SoftDeletes, BelongsToBusiness;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'sku_id',
        'custom_name',
        'custom_color_hex',
        'reorder_threshold',
        'notes',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'reorder_threshold' => 'decimal:2',
            'is_hidden' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }
}
