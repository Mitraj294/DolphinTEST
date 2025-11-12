<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

use function base_path;

class Kernel extends ConsoleKernel
{
    
    protected function schedule(Schedule $schedule): void
    {

        
        $schedule->command('tokens:cleanup --force')->dailyAt('02:00')->description('Clean up expired OAuth tokens');

        
        $schedule->command('subscriptions:update-status')->dailyAt('04:00')->description('Daily cleanup: mark expired subscriptions in DB for reporting and notifications');

        
        $schedule->command('leads:reconcile-with-users --dry-run')->dailyAt('03:00')->description('Dry-run reconcile leads with users (no updates)');

        
        
        
        
        
        
        $schedule->command('assessments:process-scheduled')->everyMinute()->description('Process organization assessment scheduled sends');
    }
    
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        
        
    }
}
