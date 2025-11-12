<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use Carbon\Carbon;

class UpdateSubscriptionStatus extends Command
{
    
    protected $signature = 'subscriptions:update-status';

    
    protected $description = 'Daily cleanup: Update the status of expired subscriptions for reporting and notifications';

    
    public function handle()
    {
        $this->info('Performing daily subscription status cleanup...');

        $expiredSubscriptions = Subscription::where('status', 'active')
            
            ->where('ends_at', '<', Carbon::now())
            ->get();

        $count = $expiredSubscriptions->count();

        if ($count > 0) {
            foreach ($expiredSubscriptions as $subscription) {
                $subscription->status = 'expired';
                $subscription->save();

                
                
            }

            $this->info("Updated {$count} expired subscription(s).");
        } else {
            $this->info('No expired subscriptions found.');
        }

        $this->info('Daily cleanup completed.');
    }
}
