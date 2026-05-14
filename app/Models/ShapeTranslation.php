<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ShapeTranslation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['shape_id', 'locale', 'name', 'description'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function shape(): BelongsTo
    {
        return $this->belongsTo(Shape::class);
    }
}
