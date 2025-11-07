<?php

namespace App\Observers;

use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Facades\Log;
use Exception;

class OrganizationObserver
{
    protected function applyLatestSubscriptionToOrg(Organization $organization)
    {
        try {
            // find latest subscription for the owner user_id
            if (empty($organization->user_id)) {
                return;
            }
            $latest = null;
            // Order by started_at (actual column) to find the latest subscription
            $latest = Subscription::where('user_id', $organization->user_id)
                ->orderBy('started_at', 'desc')
                ->first();
            if ($latest) {
                // Only update and save if contract dates actually change to avoid recursive observer calls
                $changed = false;
                if ($organization->contract_start != ($latest->started_at ?? $latest->subscription_start ?? null)) {
                    $organization->contract_start = $latest->started_at ?? $latest->subscription_start ?? null;
                    $changed = true;
                }
                if ($organization->contract_end != ($latest->ends_at ?? $latest->subscription_end ?? null)) {
                    $organization->contract_end = $latest->ends_at ?? $latest->subscription_end ?? null;
                    $changed = true;
                }
                if ($changed) {
                    $organization->save();
                }
            }
        } catch (Exception $e) {
            Log::error('Error applying latest subscription to organization', [
                'organization_id' => $organization->id,
                'user_id' => $organization->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function created(Organization $organization)
    {
        $this->applyLatestSubscriptionToOrg($organization);
    }

    public function updated(Organization $organization)
    {
        // if organization user_id changed, re-sync
        $this->applyLatestSubscriptionToOrg($organization);
    }
}
