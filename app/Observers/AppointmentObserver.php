<?php

namespace App\Observers;

use App\Appointment;

class AppointmentObserver
{
    /**
     * Listen when appointment is created and copy it to history.
     *
     * @param  \App\Appointment  $appointment
     * @return void
     */
    public function created(Appointment $appointment)
    {
        $appointment->saveToHistory();
    }

    /**
     * Listen when appointment is updated and copy it to history.
     *
     * @param  \App\Appointment  $appointment
     * @return void
     */
    public function updated(Appointment $appointment)
    {
        $appointment->saveToHistory();
    }
}
