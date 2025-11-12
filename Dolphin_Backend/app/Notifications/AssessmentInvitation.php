<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AssessmentInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    protected $assessmentLink;
    protected $assessmentName;
    protected $assessmentId;

    public function __construct(string $assessmentLink, string $assessmentName, ?int $assessmentId = null)
    {
        $this->assessmentLink = $assessmentLink;
        $this->assessmentName = $assessmentName;
        $this->assessmentId = $assessmentId;
    }

    public function via($notifiable)
    {
        
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        
        $link = $this->assessmentLink;
        $subject = 'You have been invited to an assessment: ' . ($this->assessmentName ?? '');

        return (new MailMessage())
            ->subject($subject)
            ->greeting('Hello!')
            ->line('You have been invited to take the assessment:')
            ->line('"' . ($this->assessmentName ?? '') . '"')
            ->action('Take Assessment', $link)
            ->line('If the button above does not work, copy and paste the following link into your browser:')
            ->line($link)
            ->line('Thank you for your participation!');
    }

    public function toDatabase($notifiable)
    {
        
        $linkPath = '/assessments/' . $this->assessmentId;
        return [
            'message' => 'You have a new assessment to complete: ' . $this->assessmentName,
            'link' => $linkPath,
            'assessment_id' => $this->assessmentId,
            
            'assessment_name' => $this->assessmentName,
        ];
    }
}
