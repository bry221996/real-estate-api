<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\UserAppointmentReminder;
use Notification;
use App\Appointment;

class NotifyUsersTwoHoursBeforeAppointment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $appointments;

    public function __construct()
    {
        $this->appointments = $this->getAppointmentsInTwoHours();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->appointments->each(function ($appointment) {
            $appointment->customer->notify(new UserAppointmentReminder($appointment));
            $appointment->property->agent->notify(new UserAppointmentReminder($appointment));
        });
    }

    /**
     * Get the appointments in 2 hours.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getAppointmentsInTwoHours()
    {
        return Appointment::with([ 'customer', 'property.agent' ])
            ->with(['property' => function ($query) {
                $query->without('features', 'photos');
            }])
            ->without('appointmentStatus')
            ->confirmed()
            ->whereDate('date', today()->toDateString())    
            ->whereTime('start_time', '<=', now()->addHours(2)->addMinutes(5)->toTimeString())
            ->whereTime('start_time', '>=', now()->addHours(2)->subMinutes(5)->toTimeString())
            ->get();
    }
}
