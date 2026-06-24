<?php

namespace App\Models;

use Database\Factories\DistributorProductFactory;
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
        'url',
        'price',
        'currency',
        'stock',
        'in_stock',
        'raw_data',
        'fetched_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'in_stock' => 'boolean',
        'raw_data' => 'array',
        'fetched_at' => 'datetime',
    ];

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
