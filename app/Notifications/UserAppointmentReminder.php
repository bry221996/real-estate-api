<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Channels\Semaphore\SemaphoreSmsChannel;
use App\Channels\Semaphore\SemaphoreMessage;
use App\Appointment;

class UserAppointmentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Appointment instance.
     *
     * @var \App\Appointment
     */
    public $appointment;

    /**
     * Message to be sent.
     *
     * @var string
     */
    public $message;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment;
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
        $this->appointment->property->city = str_contains(strtolower($this->appointment->property->city), 'city')
            ? $this->appointment->property->city
            : str_finish($this->appointment->property->city, ' city');

        $address = trim("House, {$this->appointment->property->street}, {$this->appointment->property->city}");
            
        if (collect([ 1, 2 ])->contains($this->appointment->property->property_type_id)) {
            $address = preg_replace('/\n\s+/', ' ', trim("
                {$this->appointment->property->unit}, {$this->appointment->property->name},
                {$this->appointment->property->city}
            "));
        }

        $message = "
            Lazatu Reminder: 
            You have an appointment in 2 hours at {$address}.
        ";

        return preg_replace('/\n\s+/', PHP_EOL, trim($message));
    }
}
