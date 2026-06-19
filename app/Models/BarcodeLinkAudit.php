<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Append-only record of a manufacturer barcode being linked to a catalog SKU
 * from the scan page. Deliberately NOT tenant-scoped (no BelongsToBusiness):
 * barcode links write to the shared catalog, so admins review them across all
 * businesses. The business_id column is kept only as context for who acted.
 */
class BarcodeLinkAudit extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    // Append-only: created_at set manually, no updated_at, never edited.
    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'user_id',
        'sku_id',
        'sku_name',
        'barcode',
        'field',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
