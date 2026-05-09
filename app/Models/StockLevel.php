<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockLevel extends Model
{
    use BelongsToBusiness, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'sku_id',
        'quantity',
        'last_movement_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'last_movement_at' => 'datetime',
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
