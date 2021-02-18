<?php

namespace App\Sorts;

class AppointmentSort extends QuerySort
{
    /**
     * Add sorting by date.
     *
     * @param string $sortOrder
     * @return void
     */
    public function date($sortOrder = 'asc')
    {
        $this->builder->orderBy('date', $sortOrder);
    }
}
