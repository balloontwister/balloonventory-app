<?php

namespace App\Models\Concerns;

use App\Scopes\BusinessScope;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope(new BusinessScope());
    }
}
