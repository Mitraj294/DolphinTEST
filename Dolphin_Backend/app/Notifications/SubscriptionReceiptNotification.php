<?php

namespace App\Notifications;

use App\Mail\SubscriptionReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReceiptNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $subscription;

    public function __construct(array $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {




        $recipient = $notifiable->routeNotificationFor('mail', $this) ?? null;

        $mailable = new SubscriptionReceipt($this->subscription);
        if ($recipient) {
            $mailable->to($recipient);
        }

        return $mailable;
    }

    public function toArray($notifiable)
    {
        return $this->subscription;
    }
}
