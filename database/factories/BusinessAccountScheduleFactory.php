<?php

use Faker\Generator as Faker;

$factory->define(App\BusinessAccountSchedule::class, function (Faker $faker) {
    return [
        'schedule_type_id' => function () {
            return App\BusinessAccountScheduleType::pluck('id')->random();
        }, 
        'setup' => function (array $schedule) {
            if ($schedule['schedule_type_id'] == 1) {
                $days = range(1, 5);
                $startTime = '09:00';
                $endTime = '18:00';
            }

            if ($schedule['schedule_type_id'] == 2) {
                $days = range(6, 7);
                $startTime = '09:00';
                $endTime = '18:00';
            }

            if ($schedule['schedule_type_id'] == 3) {
                $days = range(1, 5);
                $startTime = '18:00';
                $endTime = '00:00';
            }

            if ($schedule['schedule_type_id'] == 4) {
                $days = range(1, 5);
                $startTime = '00:00';
                $endTime = '00:00';
            }

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
        }, 
    ];
});
