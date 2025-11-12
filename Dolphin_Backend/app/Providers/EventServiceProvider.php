<?php

namespace App\Providers;

use App\Listeners\UpdateAnnouncementSentTimestamp;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Notifications\Events\NotificationSent;

class EventServiceProvider extends ServiceProvider
{
    
    protected $listen = [
        NotificationSent::class => [
            UpdateAnnouncementSentTimestamp::class,
        ],
    ];

    
    public function boot(): void
    {
        
    }

    
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
