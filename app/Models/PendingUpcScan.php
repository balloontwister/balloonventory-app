<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PendingUpcScan extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'upc_string',
        'direction',
        'quantity_scanned',
        'scanned_by_user_id',
        'scanned_at',
        'status',
        'resolved_by_user_id',
        'resolved_to_sku_id',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity_scanned' => 'decimal:2',
            'scanned_at' => 'datetime',
            'resolved_at' => 'datetime',
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

    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function resolvedToSku(): BelongsTo
    {
        return $this->belongsTo(Sku::class, 'resolved_to_sku_id');
    }
}
