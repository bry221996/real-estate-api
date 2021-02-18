<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultResource;
use App\Notifications\AppointmentRequest as AppointmentRequestNotification;
use App\Http\Requests\AppointmentRequest;
use App\Notifications\AppointmentCancelled;
use App\Property;
use Carbon\Carbon;

class CustomerCurrentBookingController extends Controller
{
    /**
     * Get current booking of authenticated user from property.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function index(Property $property)
    {
        $appointmentRequest = $property->current_booking ?? collect();

        return new DefaultResource($appointmentRequest);
    }

    /**
     * Cancel current booking appointment.
     *
     * @param \App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function cancel(Property $property)
    {
        $appointmentRequest = $property->current_booking;

        $validator = validator($appointmentRequest->toArray(), [
                'date' => 'after_or_equal:today',
                'status_id' => 'in:1,2,3', 
            ], [
                'in' => 'The booking must be confirmed, pending or rejected.',
                'start_time.after_or_equal' => 'Booking can only be cancelled 6 hours before the appointment time.', 
            ]);

        $validator->sometimes(
            'start_time', 
            'date_format:H:i|after_or_equal:' . now()->addHours(6)->format('H:i'), 
            function ($input) {
                $dateTime = Carbon::parse("{$input->date} {$input->start_time}");
                $validDate = now()->addHours(6)->second(0);

                return $validDate >= $dateTime;
            }
        );

        $validator->validate();

        try {
            $appointmentRequest->update([ 'status_id' => 4 ]);

            $property->agent->notify(new AppointmentCancelled($appointmentRequest, $property));
        } catch (\Exception $e) {
            logger()->error($e);

            abort(500, 'Something went wrong. Please contact admin');
        }

        return response([
            'message' => 'Your booking to this property is now cancelled.', 
        ]);
    }
    
    /**
     * Reschule/Update current appointment for the property.
     *
      * @param \App\Http\Requests\AppointmentRequest $request
     * @param App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function update(AppointmentRequest $request, Property $property)
    {
        $appointmentRequest = $property->current_booking;

       validator($appointmentRequest->toArray(), [
                'status_id' => 'in:1,2,3', 
                'date' => "date|after:today", 
            ], [
                'in' => 'The booking must be confirmed, pending or rejected.',
                'after' => 'Current booking should not be today or previous dates.', 
            ])
            ->validate();

        $data = $request->validated();

        $appointmentRequest->update([
            'status_id' => $appointmentRequest->status_id == 1 ? 5 : 2,
            'date' => $data['date'], 
            'start_time' => $data['start_time'], 
            'end_time' => $data['end_time'] ?? Carbon::parse($data['start_time'])->addHour()->format('H:i'), 
        ]);

        // notify
        try {
            $property->agent->notify(new AppointmentRequestNotification($appointmentRequest, $property));
        } catch (\Exception $e) {
            logger()->error($e);
        }

        return response([
            'message' => 'Booking rescheduled, waiting for verification.', 
        ]);
    }
}
