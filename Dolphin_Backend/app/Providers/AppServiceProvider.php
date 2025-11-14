<?php

namespace App\Providers;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use App\Observers\OrganizationObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Intentionally left blank — no container bindings are required at this time.
        // Keep this method to allow future early-binding of services if needed.
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
