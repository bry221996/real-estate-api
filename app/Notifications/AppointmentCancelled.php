<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Channels\Semaphore\SemaphoreSmsChannel;
use App\Channels\Semaphore\SemaphoreMessage;
use Carbon\Carbon;
use App\Appointment;
use App\Property;

class AppointmentCancelled extends Notification
{
    use Queueable;

    /**
     * Appointment instance.
     *
     * @var \App\Appointment
     */
    public $appointment;

    /**
     * Property instance.
     *
     * @var \App\Property
     */
    public $property;

    /**
     * User instance.
     * User that cancelled the appointment.
     *
     * @var \App\User
     */
    public $customer;

    /**
     * Message to send.
     *
     * @var string
     */
    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Appointment $appointment, Property $property)
    {
        $this->appointment = $appointment;
        $this->property = $property;
        $this->customer = auth()->user();
        $this->message = $this->createMessage();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [SemaphoreSmsChannel::class];
    }

    /**
     * Get the Semaphore / SMS representation of the notification.
     *
     * @param  mixed  $notifiables
     * @return SemaphoreMessage
     */
    public function toSemaphore($notifiable)
    {
        return new SemaphoreMessage($this->message);
    }

    /**
     * Create the message to be sent.
     *
     * @return void
     */
    private function createMessage()
    {
        $dateTime = Carbon::parse("{$this->appointment->date} {$this->appointment->start_time}")
            ->format('d M Y h:i A');

        $this->property->city = str_contains(strtolower($this->property->city), 'city')
            ? $this->property->city
            : str_finish($this->property->city, ' city');

        $address = trim("House, {$this->property->street}, {$this->property->city}");
            
        if (collect([ 1, 2 ])->contains($this->property->property_type_id)) {
            $address = preg_replace('/\n\s+/', ' ', trim("
                {$this->property->unit}, {$this->property->building_name},
                {$this->property->city}
            "));
        }

        $message = "Hi, Customer {$this->customer->full_name} cancelled booking for {$dateTime} at {$address}.";

        return preg_replace('/\n\s+/', PHP_EOL, trim($message));
    }
}
