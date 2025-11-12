<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenericEmail extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $subject;
    protected string $body;
    protected ?string $actionUrl = null;

    
    public function __construct(string $subject, string $body, ?string $actionUrl = null)
    {
        $this->subject = $subject;
        $this->body = $body;
        $this->actionUrl = $actionUrl;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage())->subject($this->subject)->greeting('Hello!')->line($this->body);

        if (!empty($this->actionUrl)) {
            $mail = $mail->action('Open', $this->actionUrl)
                ->line('If the button above does not work, copy and paste the following link into your browser:')
                ->line($this->actionUrl);
        }

        return $mail;
    }
}
