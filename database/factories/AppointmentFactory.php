<?php

use Faker\Generator as Faker;

$factory->define(App\Appointment::class, function (Faker $faker) {
    return [
        'property_id' => null, 
        'user_id' => null, 
        'date' => function (array $appointment) {
            if ($appointment['property_id'] !== null) {
                $property = App\Property::findOrFail($appointment['property_id']);

                $timeSlot = collect($property->schedule->setup)->filter->start_time->random();

                $date = \Carbon\Carbon::parse($timeSlot['start_time'])
                    ->startOfWeek()
                    ->addWeeks(rand(1, 5))
                    ->addDays((int) $timeSlot['day'] - 1);

                if (now()->greaterThanOrEqualTo($date)) {
                    $date = $date->addWeek();
                }

                return $date->addDay()->format('Y-m-d');
            }

            return now()->addDay()->format('Y-m-d');
        },
        'start_time' => function (array $appointment) {
            if ($appointment['property_id'] !== null) {
                $property = App\Property::findOrFail($appointment['property_id']);

                $timeSlot = collect($property->schedule->setup)->filter->start_time->first();

                return \Carbon\Carbon::parse($timeSlot['start_time'])->format('H:i');
            }

            return now()->format('H:i');
        }, 
        'end_time' => function (array $appointment) {
            return \Carbon\Carbon::parse($appointment['start_time'])
                ->addHour()
                ->format('H:i');
        }, 
    ];
});

$factory->state(App\Appointment::class, 'confirmed', function ($faker) {
    return [
        'status_id' => 1,
    ];
});

$factory->state(App\Appointment::class, 'pending', function ($faker) {
    return [
        'status_id' => 2,
    ];
});

$factory->state(App\Appointment::class, 'rejected', function ($faker) {
    return [
        'status_id' => 3,
    ];
});

$factory->state(App\Appointment::class, 'cancelled', function ($faker) {
    return [
        'status_id' => 4,
    ];
});

$factory->state(App\Appointment::class, 'reschedule', function ($faker) {
    return [
        'status_id' => 5,
    ];
});