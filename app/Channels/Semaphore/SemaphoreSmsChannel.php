<?php  

namespace App\Channels\Semaphore;

use App\Services\Semaphore;
use App\Channels\Semaphore\SemaphoreMessage;
use Illuminate\Notifications\Notification;

class SemaphoreSmsChannel
{
    /**
     * Semaphore client instance.
     *
     * @var \App\Services\Semaphore
     */
    protected $semaphore;

    /**
     * Create new semaphore channel instance.
     *
     * @param \App\Services\Semaphore $semaphore
     * @param string $from
     */
    public function __construct(Semaphore $semaphore)
    {
        $this->semaphore = $semaphore;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $to = $notifiable->routeNotificationFor('semaphore')) {
            return;
        }

        $message = $notification->toSemaphore($notifiable);

        if (is_string($message)) {
            $message = new SemaphoreMessage($message);
        }

        return $this->semaphore->send($to, $message->content);
    }
}
