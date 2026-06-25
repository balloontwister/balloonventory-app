<?php

namespace App\Models;

use Database\Factories\DistributorCatalogProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A proposed cross-distributor product cluster awaiting (or having received)
 * a catalog decision. See the migration for the review-state lifecycle and why
 * this lives on the relocatable `distributors` connection.
 */
class DistributorCatalogProposal extends Model
{
    /** @use HasFactory<DistributorCatalogProposalFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTO_APPROVED = 'auto_approved';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'upc',
        'normalized_sku',
        'status',
        'confidence',
        'proposed_brand_id',
        'proposed_balloon_size_id',
        'proposed_color_id',
        'proposed_packaging_id',
        'proposed_count',
        'proposed_name',
        'proposed_warehouse_sku',
        'evidence',
        'resulting_sku_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'proposed_count' => 'integer',
        'evidence' => 'array',
        'reviewed_at' => 'datetime',
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

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_AUTO_APPROVED, self::STATUS_REJECTED], true);
    }
}
