<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionReceipt extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $subscription;

    
    public function __construct(array $subscription)
    {
        $this->subscription = $subscription;
    }

    
    public function build()
    {
        $subject = 'Your Dolphin receipt' . (isset($this->subscription['invoice_number']) ? ' — Invoice #' . $this->subscription['invoice_number'] : '');

        return $this->subject($subject)
            ->view('mail.subscription_receipt')
            ->with(['subscription' => $this->subscription]);
    }
}
