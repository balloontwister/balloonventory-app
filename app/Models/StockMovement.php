<?php

namespace App\Models;

use App\Enums\StockDirection;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StockMovement extends Model
{
    use BelongsToBusiness, HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    // Append-only: no updated_at, no deleted_at.
    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'sku_id',
        'bin_id',
        'user_id',
        'direction',
        'full_bags_change',
        'open_bags_change',
        'upc_scanned',
        'job_id',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => StockDirection::class,
            'full_bags_change' => 'integer',
            'open_bags_change' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
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

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}
