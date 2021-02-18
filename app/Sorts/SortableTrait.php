<?php

namespace App\Sorts;

use App\Sorts\QuerySort;

trait SortableTrait
{
    /**
     * Scope query to apply sorting.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Sorts\QuerySort $querySort
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSort($query, QuerySort $querySort)
    {
        return $querySort->apply($query);
    }
}
