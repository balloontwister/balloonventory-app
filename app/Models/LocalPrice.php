<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LocalPrice extends Model
{
    use BelongsToBusiness, HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'business_id',
        'price_code',
        'amount_cents',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
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
}
