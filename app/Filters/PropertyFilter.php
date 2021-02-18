<?php

namespace App\Filters;

class PropertyFilter extends QueryFilter
{
    /**
     * Add filter property offers by type.
     *
     * @param string $type
     * @return void
     */
    public function offerType(string $type)
    {
        $this->builder->whereHas('offerType', function ($query) use ($type) {
            if ($type == 'all') {
                return $query;
            }

            $query->where('property_offer_types.name', 'like' , '%' . $type . '%');
        });
    }

    /**
     * Add filter property by type.
     *
     * @param string $type
     * @return void
     */
    public function propertyType(string $type)
    {
        $this->builder->whereHas('propertyType', function ($query) use ($type) {
            if ($type == 'all') {
                return $query;
            }

            $query->where('property_types.name', 'like' , '%' . $type . '%');
        });
    }

    /**
     * Add filter property by minimum price.
     *
     * @param float $value
     * @return void
     */
    public function minPrice(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.price', '>=', $value);
    }

    /**
     * Add filter property by maximum price.
     *
     * @param float $value
     * @return void
     */
    public function maxPrice(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.price', '<=', $value);
    }
    
    /**
     * Add filter property by minimum lot size.
     *
     * @param float $value
     * @return void
     */
    public function minLotSize(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.lot_size', '>=', $value);
    }
        
    /**
     * Add filter property by maximum lot size.
     *
     * @param float $value
     * @return void
     */
    public function maxLotSize(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.lot_size', '<=', $value);
    }

    /**
     * Add filter property by minimum bedrom count.
     *
     * @param float $value
     * @return void
     */
    public function minBedroom(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.bedroom_count', '>=', $value);
    }

    /**
     * Add filter property by minimum bathroom count.
     *
     * @param float $value
     * @return void
     */
    public function minBathroom(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.bathroom_count', '>=', $value);
    }

    /**
     * Add filter property by minimum garage count.
     *
     * @param float $value
     * @return void
     */
    public function minGarage(float $value)
    {
        $value = $value >= 0 ? $value : 0;

        $this->builder->where('properties.garage_count', '>=', $value);
    }

    /**
     * Add filter property by name.
     *
     * @param string $value
     * @return void
     */
    public function name(string $value)
    {
        $this->builder->orWhere('properties.name', 'like', "%{$value}%");
    }

    /**
     * Add filter property by address.
     *
     * @param string $value
     * @return void
     */
    public function address(string $value)
    {
        $this->builder->orWhere('properties.address', 'like', "%{$value}%");
    }

    /**
     * Add filter property by building name.
     *
     * @param string $value
     * @return void
     */
    public function buildingName(string $value)
    {
        $this->builder->orWhere('properties.building_name', 'like', "%{$value}%");
    }

    /**
     * Add filter property by listing ID.
     *
     * @param string $value
     * @return void
     */
    public function listingId(string $value)
    {
        $this->builder->orWhere('properties.listing_id', 'like', "%{$value}%");
    }

    /**
     * Add filter property by creator.
     *
     * @param int $value
     * @return void
     */
    public function createdBy(int $value)
    {
        $this->builder->where('properties.created_by', $value);
    }

    /**
     * Add filter property by status name.
     *
     * @param string $type
     * @return void
     */
    public function propertyStatus(string $type = null)
    {
        if ((! auth()->check()) || auth()->user()->roles->pluck('name')->diff([ 'customer' ])->isEmpty()) {
            return $this->builder->where('property_status_id', 1);
        }

        $statuses = collect(explode(',', $type))->intersect([
            'published',
            'pending',
            'rejected',
            'closed',
            'expired',
        ]);

        // when empty just search for no result
        if ($statuses->isEmpty()) {
            $this->builder->where('property_status_id', $type);
        }

        $statuses->each(function ($status) {
            $this->builder->orWhere(function ($query) use ($status) {
                $query->scopes([ $status ]);
            });
        });
    }
}