<?php

namespace App\Sorts;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

abstract class QuerySort
{
    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Attributes that will used for sorting.
     *
     * @var array
     */
    protected $sortKeys;

    /**
     * The Eloquent builder.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $builder;

    /**
     * Create a new sort instance.
     *
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->sortKeys = explode(',', $request->sort);
    }

    /**
     * Apply the sorting order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        collect($this->sortKeys)->each(function ($value) {
            $method = camel_case(str_replace_first('-', '', $value));

            if (method_exists($this, $method)) {
                $sortOrder = starts_with($value, '-') ? 'desc' : 'asc';

                $this->$method($sortOrder);
            }
        });

        return $this->builder;
    }

    /**
     * Fetch all relevant sort key from the request.
     *
     * @return array
     */
    public function getSortKeys()
    {
        return $this->sortKeys ?? [];
    }
}