<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    
    protected $policies = [
        \App\Models\User::class => \App\Policies\UserPolicy::class,
    ];

    
    public function boot()
    {
        $this->registerPolicies();

        
        Passport::enablePasswordGrant();

        
        Passport::tokensCan([
            'impersonate' => 'Act as another user (impersonation)',
        ]);

        if (request()->boolean('remember')) {
            Passport::tokensExpireIn(now()->addDays(7));
            Passport::refreshTokensExpireIn(now()->addDays(30));
        } else {
            Passport::tokensExpireIn(now()->addHours(8));
            Passport::refreshTokensExpireIn(now()->addDays(7));
        }

        
        Passport::personalAccessTokensExpireIn(now()->addMonths(2));
    }
}
