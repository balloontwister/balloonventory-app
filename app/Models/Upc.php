<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Upc extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'upc_string',
        'sku_id',
        'first_added_by_business_id',
        'first_added_by_user_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function sku(): BelongsTo
    {
        return $this->belongsTo(Sku::class);
    }

    public function firstAddedByBusiness(): BelongsTo
    {
        return $this->belongsTo(Business::class, 'first_added_by_business_id');
    }

    public function firstAddedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'first_added_by_user_id');
    }
}
