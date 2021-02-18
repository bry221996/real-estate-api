<?php

namespace App\Filters;

class AppointmentFilter extends QueryFilter
{
    /**
     * Apply filter by status name.
     *
     * @param string $values
     * @return void
     */
    public function status(string $values)
    {
        $this->builder->whereHas('appointmentStatus', function ($query) use ($values) {
            $query->whereIn('appointment_statuses.name', explode(',', $values));
        });
    }

    /**
     * Apply filter by status_id.
     *
     * @param string $values
     * @return void
     */
    public function statusId(string $values)
    {
        $this->builder->whereIn('status_id', explode(',', $values));
    }

    /**
     * Apply filter by date word.
     *
     * @param string $values
     * @return void
     */
    public function dateWord(string $keyWord)
    {
        $this->builder->when($keyWord == strtolower('recent'), function ($query) {
                $query->whereDate('date', '>', now()->subWeek()->toDateString());
            })
            ->when($keyWord == strtolower('today'), function ($query) {
                $query->whereDate('date', now()->toDateString());
            });
    }

    /**
     * Apply filter by property address.
     *
     * @param string $value
     * @return void
     */
    public function address(string $value)
    {
        $this->builder->whereHas('property', function ($query) use ($value) {
            $query->where('properties.address', 'like', "%{$value}%")
                ->orWhere('properties.formatted_address', 'like', "%{$value}%");
        });
    }
}