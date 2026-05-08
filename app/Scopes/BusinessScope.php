<?php

namespace App\Scopes;

use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class BusinessScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if ($id = BusinessContext::currentId()) {
            $builder->where($model->getTable().'.business_id', $id);
        }
    }
}
