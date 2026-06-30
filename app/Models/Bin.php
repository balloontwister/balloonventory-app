<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Bin extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'location_id',
        'number',
        'number_locked',
        'name',
        'description',
        'scan_code',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'number_locked' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }

            if (empty($model->scan_code)) {
                $model->scan_code = 'BIN-'.strtoupper(Str::random(8));
            }
        });

        static::deleting(function (self $model) {
            if ($model->is_default) {
                throw new \RuntimeException('The Default bin cannot be deleted.');
            }
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }
}
