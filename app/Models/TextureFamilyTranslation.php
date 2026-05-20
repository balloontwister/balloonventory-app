<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TextureFamilyTranslation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['texture_family_id', 'locale', 'name', 'description'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid7();
            }
        });
    }

    public function textureFamily(): BelongsTo
    {
        return $this->belongsTo(TextureFamily::class);
    }
}
