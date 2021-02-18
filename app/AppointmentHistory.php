<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppointmentHistory extends Model
{
    /**
     * Ignore the updated_at attribute from the appointment.
     *
     * @var array
     */
    protected $guarded = [ 'updated_at' ];
}
