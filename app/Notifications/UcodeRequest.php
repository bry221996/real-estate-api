<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Channels\Semaphore\SemaphoreSmsChannel;
use App\Channels\Semaphore\SemaphoreMessage;

class UcodeRequest extends Notification
{
    use Queueable;

    /**
     * Ucode of the receiver.
     *
     * @var int
     */
    public $code;

    /**
     * Create a new notification instance.
     *
     * @param int $code
     * @return void
     */
    public function __construct($code)
    {
        $this->code = $code;
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
        return new SemaphoreMessage('Your LAZATU verification code is: ' . $this->code);
    }
}
