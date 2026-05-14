<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ColorFamilyTranslation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['color_family_id', 'locale', 'name', 'description'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function colorFamily(): BelongsTo
    {
        return $this->belongsTo(ColorFamily::class);
    }
}
