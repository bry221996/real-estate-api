<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultCollection;
use App\User;
use App\Filters\AppointmentFilter;
use App\Sorts\AppointmentSort;

class UserAppointmentController extends Controller
{
    /**
     * Get list of user appointments.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Filters\AppointmentFilter $filters
     * @param \App\Sorts\AppointmentSort $sorts
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, AppointmentFilter $filters, AppointmentSort $sorts, ?User $user)
    {
        $user = empty($user->getOriginal()) ? auth()->user() : $user;

        $requestQueryScope = $request->filled('scope')
            ? collect(explode(',', $request->scope))
            : collect();

        $appointmentsQuery = $user->appointments()->with('histories');

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

        collect($appointments->items())->each(function ($appointment) use ($requestQueryScope) {
            if ($requestQueryScope->contains('with_previous_details')) {
                $appointment->append([
                    'previous_appointment_details',
                    'is_rescheduled_from_confirmed_status',
                ]);
            }

            $appointment->makeHidden('histories');
        });

        return new DefaultCollection($appointments);
    }
}
