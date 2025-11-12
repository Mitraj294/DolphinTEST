<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Subscription;
use App\Models\Organization;
use App\Models\User;
use App\Observers\SubscriptionObserver;
use App\Observers\OrganizationObserver;
use App\Observers\UserObserver;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    
    public function register(): void
    {
        
    }

    
    public function boot(): void
    {
        
        $this->app['view']->addNamespace('mail', resource_path('views/vendor/mail'));

        
        Subscription::observe(SubscriptionObserver::class);
        Organization::observe(OrganizationObserver::class);
        User::observe(UserObserver::class);

        
        Passport::enablePasswordGrant();

        
        
        \Laravel\Passport\Passport::tokensExpireIn(now()->addHours(8));
        \Laravel\Passport\Passport::refreshTokensExpireIn(now()->addDays(7));
        \Laravel\Passport\Passport::personalAccessTokensExpireIn(now()->addMonths(2));
    }
}
