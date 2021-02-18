<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Filters\FilterableTrait;
use App\Sorts\SortableTrait;
use App\User;
use App\Property;

class Appointment extends Model
{
    use FilterableTrait, SortableTrait;

    protected $guarded = [];

    protected $with = [ 'appointmentStatus' ]; 

    protected $hidden = [ 'appointmentStatus' ]; 

    protected $appends = [ 'status' ]; 

    /**
     * Get status of appointment.
     */
    public function appointmentStatus()
    {
        return $this->belongsTo(AppointmentStatus::class, 'status_id');
    }

    /**
     * Scope query to return confirmed appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status_id', 1);
    }

    /**
     * Scope query to return pending appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status_id', 2);
    }

    /**
     * Scope query to return rejected appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRejected($query)
    {
        return $query->where('status_id', 3);
    }

    /**
     * Scope query to return cancelled appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCancelled($query)
    {
        return $query->where('status_id', 4);
    }

    /**
     * Scope query to return rescheduled appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeReschedule($query)
    {
        return $query->where('status_id', 5);
    }

    /**
     * Scope query to return completed appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCompleted($query)
    {
        return $query->where('status_id', 6);
    }
    
    /**
     * Scope query to return expired appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where('status_id', 7);
    }

    /**
     * Reformat start_time to H:i.
     *
     * @param string $value
     * @return string
     */
    public function getStartTimeAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }

    /**
     * Reformat end_time to H:i.
     *
     * @param string $value
     * @return string
     */
    public function getEndTimeAttribute($value)
    {
        return Carbon::parse($value)->format('H:i');
    }

    /**
     * Get the name of the status
     *
     * @return string
     */
    public function getStatusAttribute()
    {
        return $this->relations['appointmentStatus']->name;
    }

    /**
     * Get related property.
     */
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    /**
     * Get related customer.
     */
    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Scope query to get where date and start_time base on parameter.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $dateTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeStartDateTime($query, Carbon $dateTime)
    {
        $query->where('date', '>=', $dateTime->toDateString())
            ->where('start_time', '>=', $dateTime->toTimeString());
    }

    /**
     * Scope query to get where date and start_time base on parameter.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $dateTime
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndDateTime($query, Carbon $dateTime)
    {
        $query->where('date', '<=', $dateTime->toDateString())
            ->where('end_time', '<=', $dateTime->toTimeString());
    }

    /**
     * Get appointment histories.
     */
    public function histories()
    {
        return $this->hasMany(AppointmentHistory::class, 'appointment_id');
    }

    /**
     * Save current appointment details to history.
     *
     * @return void
     */ 
    public function saveToHistory()
    {
        $data = collect($this->fresh()->getOriginal())
            ->forget('id')
            ->toArray();

        $this->histories()->create($data );
    }

    /**
     * Check if the current appointment is rescheduled from confirmed status.
     *
     * @return bool
     */
    public function getIsRescheduledFromConfirmedStatusAttribute()
    {
        $this->loadMissing('histories');

        $histories = $this->histories->sortByDesc('id')->take(2);

        if ($histories->count() < 2) {
            return false;
        }

        return $histories->first()->status_id == 5 && $histories->last()->status_id == 1;
    }

    /**
     * Get the previous value of the appointment.
     *
     * @return \App\AppointmentHistory
     */
    public function getPreviousAppointmentDetailsAttribute()
    {
        $this->loadMissing('histories');

        $histories = $this->histories->sortByDesc('id')->take(2);

        if ($histories->count() < 2) {
            return null;
        }

        return $histories->last();
    }

    /**
     * Set date attribute format.
     *
     * @param string $value
     * @return void
     */
    public function setDateAttribute($value)
    {
        $this->attributes['date'] = Carbon::parse($value)->format('Y-m-d');
    }

    /**
     * Set start_time attribute format.
     *
     * @param string $value
     * @return void
     */
    public function setStartTimeAttribute($value)
    {
        $this->attributes['start_time'] = Carbon::parse($value)->format('H:i');
    }

    /**
     * Set end_time attribute format.
     *
     * @param string $value
     * @return void
     */
    public function setEndTimeAttribute($value)
    {
        $this->attributes['end_time'] = Carbon::parse($value)->format('H:i');
    }

    /**
     * Scope query to get pass appointments.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $date
     * @param string $time
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeIsPast($query, $date = null, $time = null)
    {
        $date = $date ?? today()->toDateString();
        $time = $time ?? now()->format('H:i');

        return $query->where(function ($query) use ($date, $time) {
            $query->where(function ($query) use ($date) {
                $query->whereDate('date', '<', $date);
            })
            ->orWhere(function ($query) use ($date, $time) {
                $query->whereDate('date', $date)
                    ->whereTime('end_time', '<=', $time);
            });
        });
    }
}
