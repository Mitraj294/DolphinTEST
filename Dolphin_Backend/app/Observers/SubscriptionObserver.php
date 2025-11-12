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
                
                $org->contract_start = $subscription->started_at ?? $subscription->subscription_start ?? null;
                $org->contract_end = $subscription->ends_at ?? $subscription->subscription_end ?? null;
                $org->save();
            }
        } catch (Exception $e) {
            // Log the exception so issues during org update are visible and do not fail the observer
            
            try {
                \Illuminate\Support\Facades\Log::warning('[SubscriptionObserver] failed to update organizations from subscription', ['error' => $e->getMessage()]);
            } catch (Exception $_) {
                // swallow logging errors to avoid cascading failures from observers
            }
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
