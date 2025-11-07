<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Models\Organization;
use Exception;

class SubscriptionObserver
{
    protected function updateOrgsFromSubscription(Subscription $subscription)
    {
        if (empty($subscription->user_id)) {
            return;
        }
        try {
            $orgs = Organization::where('user_id', $subscription->user_id)->get();
            foreach ($orgs as $org) {
                // Subscription model uses started_at / ends_at
                $org->contract_start = $subscription->started_at ?? $subscription->subscription_start ?? null;
                $org->contract_end = $subscription->ends_at ?? $subscription->subscription_end ?? null;
                $org->save();
            }
        } catch (Exception $e) {
            // swallow errors to avoid breaking subscription flow; log if you have a logger
        }
    }

    public function created(Subscription $subscription)
    {
        $this->updateOrgsFromSubscription($subscription);
    }

    public function updated(Subscription $subscription)
    {
        $this->updateOrgsFromSubscription($subscription);
    }

    public function restored(Subscription $subscription)
    {
        $this->updateOrgsFromSubscription($subscription);
    }
}
