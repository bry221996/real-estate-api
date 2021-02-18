<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultCollection;
use App\Http\Resources\DefaultResource;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRejected;
use Illuminate\Support\Facades\Notification;
use App\Appointment;
use App\Filters\AppointmentFilter;
use App\Sorts\AppointmentSort;

class AppointmentController extends Controller
{
    /**
     * Get all appointments.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Sorts\AppointmentSort $sorts
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, AppointmentFilter $filters, AppointmentSort $sorts)
    {
        $appointmentsQuery = Appointment::query();

        if ($request->filled('include')) {
            $includes = collect(explode(',', $request->include))
                ->intersect([
                    'property',
                    'property.agent',
                    'customer',
                ]);

            $appointmentsQuery->with($includes->toArray());
        }

        $appointmentsQuery->filter($filters)->sort($sorts);

        $appointments = $appointmentsQuery->latest()->paginate($request->per_page ?? 10);

        return new DefaultCollection($appointments);
    }

    /**
     * Confirm the booking and reject the remaining appointment with the same booking.
     *
     * @param \App\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function confirm(Appointment $appointment)
    {
        validator($appointment->toArray(), [
                'status_id' => 'in:2,5', 
            ], [
                'in' => 'The appointment must be pending or rescheduled', 
            ])
            ->validate();

        $appointment->update([ 'status_id' => 1 ]);

        $duplicateAppointmentsQuery = Appointment::with('customer')
            ->where([
                [ 'property_id', $appointment->property_id ], 
                [ 'date', $appointment->date ], 
                [ 'start_time', $appointment->start_time ], 
                [ 'end_time', $appointment->end_time ], 
                [ 'id', '!=', $appointment->id ], 
            ]);

        // reject other appointments
        $duplicateAppointmentsQuery->update([ 'status_id' => 3 ]);

        $rejectedCustomers = $duplicateAppointmentsQuery->get()->map(function ($appointment) {
            return $appointment->customer;
        });

        // sms notification
        try {
            $appointment->load('property');

            $appointment->customer->notify(new AppointmentConfirmed($appointment));

            if ($rejectedCustomers->isNotEmpty()) {
                Notification::send($rejectedCustomers, new AppointmentRejected($appointment));
            }
        } catch (\Exception $e) {
            logger()->error($e);
        }

        return response([
            'message' => 'Appointment Confirmed', 
        ]);
    }

    /**
     * Reject the appointment.
     *
     * @param \App\Appointment $appointment
     * @return \Illuminate\Http\Response
     */
    public function reject(Appointment $appointment)
    {
        validator($appointment->toArray(), [
                'status_id' => 'in:2,5', 
            ], [
                'in' => 'The appointment must be pending or rescheduled', 
            ])
            ->validate();

        $appointment->update([ 'status_id' => 3 ]);

        try {
            $appointment->load('property');

            $appointment->customer->notify(new AppointmentRejected($appointment));
        } catch (\Exception $e) {
            logger()->error($e);

            abort(500, 'Something went wrong. Please contact admin.');
        }

        return response([
            'message' => 'Appointment Rejected.', 
        ]);
    }
}
