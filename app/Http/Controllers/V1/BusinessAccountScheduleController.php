<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Resources\DefaultCollection;
use App\Http\Resources\DefaultResource;
use App\BusinessAccountSchedule;
use App\User;
use App\Rules\UniqueSchedule;

class BusinessAccountScheduleController extends Controller
{
    /**
     * List all schedules.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\User $user
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, ?User $user)
    {
        $user = empty($user->getOriginal()) ? auth()->user() : $user;

        $schedule = optional($user->schedule);

        $weeklySchedule = optional($user->schedule)->setup;

        $weeklySchedule = collect($weeklySchedule);

        $response = new DefaultResource($weeklySchedule);

        if ($weeklySchedule->isEmpty()) {
            return $response->additional([
                'message' => 'Schedule not configured.', 
            ]);
        }

        return $response->additional([
            'meta' => [
                'schedule_type_id' => $schedule->schedule_type_id, 
            ], 
        ]);
    }

    /**
     * Create schedules.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_type_id' => 'required|numeric|exists:business_account_schedule_types,id', 
        ]);

        $sheduleDetails = $this->getScheduleDetails($data['schedule_type_id']);

        $weekSchedule = $this->generateWeekSchedule(
            $sheduleDetails['days'],
            $sheduleDetails['start_time'],
            $sheduleDetails['end_time']
        );

        if (auth()->user()->schedule()->count() == 0) {
            auth()->user()->schedule()->create([
                'schedule_type_id' => $data['schedule_type_id'], 
                'setup' => $weekSchedule, 
            ]);
        }
        
        return response([
            'message' => 'Schedule\'s created.', 
        ]);
    }

    /**
     * Update schedules.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'schedule_type_id' => 'required|numeric|exists:business_account_schedule_types,id', 
        ]);

        $sheduleDetails = $this->getScheduleDetails($data['schedule_type_id']);

        $weekSchedule = $this->generateWeekSchedule(
            $sheduleDetails['days'],
            $sheduleDetails['start_time'],
            $sheduleDetails['end_time']
        );

        auth()->user()->schedule()->first()->update([
            'schedule_type_id' => $data['schedule_type_id'], 
            'setup' => $weekSchedule, 
        ]);

        return response([
            'message' => 'Schedule\'s updated.', 
        ]);
    }

    /**
     * Validate request.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    private function validateRequest(Request $request)
    {
        $request->validate([ 
            'schedules' => 'required|array', 
        ]);
            
        $validator = validator($request->all(), [
            'schedules.*.day' => 'required|between:1,7|numeric', 
            'schedules.*.start_time' => 'required|date_format:H:i', 
            'schedules.*.end_time' => 'required|date_format:H:i', 
        ]);

        $validator->validate();

        return $request->only(array_keys($validator->getRules()));
    }

    /**
     * Get summary for schedule type id.
     *
     * @param int $type [1 - 4]
     * @return array
     */
    private function getScheduleDetails($type = 1)
    {
        if ($type == 1) {
            $days = range(1, 5);
            $startTime = '09:00';
            $endTime = '18:00';
        }

        if ($type == 2) {
            $days = range(6, 7);
            $startTime = '09:00';
            $endTime = '18:00';
        }

        if ($type == 3) {
            $days = range(1, 5);
            $startTime = '18:00';
            $endTime = '00:00';
        }

        if ($type == 4) {
            $days = range(1, 5);
            $startTime = '00:00';
            $endTime = '00:00';
        }

        return [
            'days' => $days, 
            'start_time' => $startTime, 
            'end_time' => $endTime, 
        ];
    }

    /**
     * Generate empty week schedule.
     *
     * @return array
     */
    private function generateWeekSchedule($days = [], $startTime = '09:00', $endTime = '18:00')
    {
        return collect(range(1, 7))->transform(function ($day) use ($days, $startTime, $endTime) {
                $startTime = in_array($day, $days) ? $startTime : null;
                $endTime = in_array($day, $days) ? $endTime : null;

                return [
                    'day' => $day, 
                    'start_time' => $startTime, 
                    'end_time' => $endTime, 
                ];
            })
            ->toArray();
    }
}
