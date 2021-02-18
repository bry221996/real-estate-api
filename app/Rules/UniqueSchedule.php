<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\BusinessAccountSchedule;

class UniqueSchedule implements Rule
{
    /**
     * Current schedules.
     *
     * @var array
     */
    protected $schedules = [];

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->schedules = auth()->user()->schedules;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        dump($value['start_time']);
        $startTimeAcceptableRange = BusinessAccountSchedule::where([
                [ 'start_time', '<', $value['start_time'] ],
                [ 'day', $value['day'] ],
            ])
            ->orderBy('start_time', 'desc')
            ->limit(1)
            ->union(
                BusinessAccountSchedule::where([
                        [ 'start_time', '>=', $value['start_time'] ],
                        [ 'day', $value['day'] ],
                    ])
                    ->orderBy('start_time', 'asc')
                    ->limit(1)
            )
            ->get();

        dd($startTimeAcceptableRange->toArray());

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The validation error message.';
    }
}
