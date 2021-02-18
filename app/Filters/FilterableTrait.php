<?php

namespace App\Filters;

use App\Filters\QueryFilter;

trait FilterableTrait
{
    /**
     * Scope query to apply filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Filters\QueryFilter $queryFilter
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilter($query, QueryFilter $queryFilter)
    {
        return $query->where(function ($query) use ($queryFilter) {
            $query = $queryFilter->apply($query);
        });
    }
}
