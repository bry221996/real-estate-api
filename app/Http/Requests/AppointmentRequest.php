<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $confirmedAppointment = $this->property->appointments()->where([
                'date' => $this->date, 
                'start_time' => $this->start_time, 
            ])
            ->confirmed()
            ->first();

        if (! empty($confirmedAppointment)) {
            abort(422, 'The apointment schedule is already taken.');
        }

        return [
            'date' => 'required|date_format:Y-m-d|after_or_equal:tomorrow', 
            'start_time' => 'required|date_format:H:i', 
            'end_time' => 'sometimes|date_format:H:i|after:start_time', 
        ];
    }
}
