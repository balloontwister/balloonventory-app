<?php

namespace App\Models;

use App\Services\Distributors\DistributorLearnedAliasStore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A learned raw-distributor-value → catalog-reference mapping, captured from an
 * admin's correction in the proposal review queue. See the migration for the
 * scoping rules and why this lives on the relocatable `distributors` connection.
 *
 * Read/written through {@see DistributorLearnedAliasStore},
 * which normalizes keys and caches the table in memory for the matcher.
 */
class DistributorLearnedAlias extends Model
{
    public const ATTRIBUTES = ['brand', 'size', 'color', 'packaging'];

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'distributor_id',
        'attribute',
        'brand_id',
        'raw_value_normalized',
        'catalog_id',
        'note',
        'created_by',
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
}
