<?php

namespace App\Models;

use Database\Factories\DistributorProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A raw product listing staged from a distributor. See the migration for why
 * this lives on the relocatable `distributors` connection with no DB foreign
 * keys.
 */
class DistributorProduct extends Model
{
    /** @use HasFactory<DistributorProductFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'distributor_id',
        'external_id',
        'raw_sku',
        'normalized_sku',
        'upc',
        'title',
        'product_type',
        'url',
        'price',
        'currency',
        'stock',
        'in_stock',
        'raw_data',
        'fetched_at',
        'last_seen_at',
        'removed_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'in_stock' => 'boolean',
        'raw_data' => 'array',
        'fetched_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'removed_at' => 'datetime',
    ];

    /**
     * Products still listed in the distributor's catalog (not retired by a
     * sitemap removal). Only these should feed clustering / proposals.
     *
     * @param  Builder<DistributorProduct>  $query
     */
    public function scopeActive($query): void
    {
        $query->whereNull('removed_at');
    }

    public function getConnectionName(): ?string
    {
        return config('distributors.connection');
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

    /**
     * The distributor lives on the primary connection, so this relation only
     * resolves when staging shares that connection. App code should prefer
     * loading distributors by id when the data is relocated.
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }
}
