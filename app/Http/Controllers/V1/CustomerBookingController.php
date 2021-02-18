<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultCollection;
use App\Notifications\AppointmentRequest as AppointmentRequestNotification;
use App\Http\Requests\AppointmentRequest;
use Notification;
use Carbon\Carbon;
use App\Appointment;
use App\Property;
use App\User;
use App\Filters\AppointmentFilter;
use App\Sorts\AppointmentSort;

class CustomerBookingController extends Controller
{
    /**
     * Get customer bookings.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Filters\AppointmentFilter $filters
     * @param \App\Sorts\AppointmentSort $sorts
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, AppointmentFilter $filters, AppointmentSort $sorts)
    {
        $appointmentsQuery = auth()->user()->bookings()->with('histories');

        $requestQueryScope = $request->filled('scope')
            ? collect(explode(',', $request->scope))
            : collect();

        if ($request->filled('include')) {
            $includes = collect(explode(',', $request->include))
                ->intersect([
                    'property',
                    'property.agent',
                ]);

            $appointmentsQuery->with($includes->toArray());
        }

        $appointmentsQuery->filter($filters)->sort($sorts);

        $bookings = $appointmentsQuery->latest()->paginate($request->per_page ?? 10);

        collect($bookings->items())->each(function ($booking) use ($requestQueryScope) {
            if ($requestQueryScope->contains('with_previous_details')) {
                $booking->append([
                    'previous_appointment_details',
                    'is_rescheduled_from_confirmed_status',
                ]);
            }

            $booking->makeHidden('histories');
        });

        return new DefaultCollection($bookings);
    }

    /**
     * Create an appointment for the property.
     *
     * @param \App\Http\Requests\AppointmentRequest $request
     * @param App\Property $property
     * @return \Illuminate\Http\Response
     */
    public function store(AppointmentRequest $request, Property $property)
    {
        $data = $request->validated();

        $currentBooking = $this->getCurrentBooking($property->id) ?? collect();

        validator($currentBooking->toArray(), [
                'status_id' => 'not_in:1,2,5', 
             ], [
                'not_in' => 'You curently have a booking to this property.',
             ])
             ->validate();

        $appointment = Appointment::create([
            'user_id' => auth()->id(), 
            'property_id' => $property->id, 
            'date' => $data['date'], 
            'start_time' => $data['start_time'], 
            'end_time' => $data['end_time'] ?? Carbon::parse($data['start_time'])->addHour()->toDateTimeString(), 
        ]);

        // notify
        try {
            $usersToNotify = User::isAdmin()->get();

            $usersToNotify->push($property->agent);

            Notification::send($usersToNotify, new AppointmentRequestNotification($appointment, $property));
        } catch (\Exception $e) {
            logger()->error($e);
        }

        return response([
            'message' => 'Booking submitted, waiting for verification.', 
        ]);
    }

    /**
     * Get authenticated user current booking to property.
     *
     * @param int $propertyId
     * @return \App\Appointment
     */
    private function getCurrentBooking($propertyId)
    {
        return auth()->user()->bookings()
            ->latest()
            ->where('property_id', $propertyId)
            ->first();
    }
}
