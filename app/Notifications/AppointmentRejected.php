<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Channels\Semaphore\SemaphoreSmsChannel;
use App\Channels\Semaphore\SemaphoreMessage;
use Carbon\Carbon;
use App\Appointment;

class AppointmentRejected extends Notification
{
    use Queueable;

    /**
     * Appointment instance.
     *
     * @var \App\Appointment
     */
    public $appointment;

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
        $dateTime = Carbon::parse("{$this->appointment->date} {$this->appointment->start_time}")
            ->format('d M Y h:i A');

        $this->appointment->property->city = str_contains(strtolower($this->appointment->property->city), 'city')
            ? $this->appointment->property->city
            : str_finish($this->appointment->property->city, ' city');

        $address = trim("House, {$this->appointment->property->street}, {$this->appointment->property->city}");
            
        if (collect([ 1, 2 ])->contains($this->appointment->property->property_type_id)) {
            $address = preg_replace('/\n\s+/', ' ', trim("
                {$this->appointment->property->unit}, {$this->appointment->property->building_name},
                {$this->appointment->property->city}
            "));
        }

        $message = "
            Hi Lazatu Customer, 

            Your booking request for {$dateTime} at {$address} has been declined. You may reschedule your booking.
        ";

        return preg_replace('/\n\s+/', PHP_EOL, trim($message));
    }
}
