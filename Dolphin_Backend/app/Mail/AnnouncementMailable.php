<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnnouncementMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $announcement;
    public $displayName;
    public $subjectLine;
    public $actionText;
    public $actionUrl;

    
    public function __construct($announcement, $displayName = null, $subject = null, $actionText = null, $actionUrl = null)
    {
        $this->announcement = $announcement;
        $this->displayName = $displayName;
        $this->subjectLine = $subject ?? ($announcement->subject ?? 'New Announcement');
        $this->actionText = $actionText;
        $this->actionUrl = $actionUrl;
    }

    
    public function build()
    {
        
        $introLines = [$this->announcement->body];
        $outroLines = [];
        $greeting = $this->displayName ? ("Hello " . $this->displayName . ",") : null;
        $displayableActionUrl = $this->actionUrl;

        return $this->subject($this->subjectLine)
            
            ->view('vendor.notifications.email')
            ->text('vendor.notifications.email_plain')
            ->with([
                'announcement' => $this->announcement,
                'greeting' => $greeting,
                'introLines' => $introLines,
                'outroLines' => $outroLines,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
                'displayableActionUrl' => $displayableActionUrl,
                'salutation' => null,
                'logoUrl' => config('app.logo_url') ?? null,
                'level' => null,
            ]);
    }
}
