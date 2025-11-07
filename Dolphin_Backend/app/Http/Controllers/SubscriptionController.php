<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subscription;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SubscriptionController extends Controller
{

    /**
     * Check if a subscription has expired.
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function hasExpired(Subscription $subscription): bool
    {
        // If no end date is set, consider it as never expiring
        if (!$subscription->ends_at) {
            return false;
        }

        // Compare the end date with the current time
        return $subscription->ends_at->isPast();
    }

    /**
     * Check if a subscription is currently active (not expired).
     *
     * @param Subscription $subscription
     * @return bool
     */
    public function isActive(Subscription $subscription): bool
    {
        return $subscription->isActive() && !$this->hasExpired($subscription);
    }

    /**
     * Update subscription status if it has expired and return the status.
     *
     * @param Subscription $subscription
     * @return bool True if active, false if expired
     */
    public function checkAndUpdateStatus(Subscription $subscription): bool
    {
        if ($this->hasExpired($subscription) && $subscription->status === 'active') {
            $subscription->update(['status' => 'canceled']);
            return false;
        }

        return $subscription->status === 'active';
    }

    //Get the current active subscription plan for the relevant user.
    //Accessible by the user or by a superadmin viewing a specific organization.

    public function getCurrentPlan(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json(null);
        }

        $currentSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('created_at')
            ->first();

        if (!$currentSubscription) {
            return response()->json(null);
        }

        // The formatting logic is now handled by the Subscription model's accessors.
        return response()->json($this->formatPlanPayload($currentSubscription));
    }

    //Get the entire billing history for the relevant user.

    public function getBillingHistory(Request $request)
    {
        $user = $this->resolveUser($request);

        if (!$user) {
            return response()->json([]);
        }

        $history = $user->subscriptions()
            ->latest('created_at')
            ->get()
            ->map(fn($subscription) => $this->formatHistoryPayload($subscription));

        return response()->json($history);
    }

    //Get a simple subscription status for the relevant user.

    public function subscriptionStatus(Request $request)
    {
        $user = $this->resolveUser($request);

        $subscription = $user?->subscriptions()->latest('created_at')->first();
        $latestInvoice = $subscription?->invoices()->first();

        return response()->json([
            'status' => $subscription?->status ?? 'none',
            'plan_id' => $subscription?->plan_id,
            'subscription_id' => $subscription?->id,
            'started_at' => $subscription?->started_at?->toDateTimeString(),
            'ends_at' => $subscription?->ends_at?->toDateTimeString(),
            'current_period_end' => $subscription?->current_period_end?->toDateTimeString(),
            'is_paused' => $subscription?->is_paused ?? false,
            'cancel_at_period_end' => $subscription?->cancel_at_period_end ?? false,
            'latest_amount_paid' => $latestInvoice?->amount_paid,
            'currency' => $latestInvoice?->currency,
        ]);
    }

    /*

    | Helper & Formatting Methods

    */

    /**
     * Resolve the user for the request.
     * If the requester is a superadmin and an org_id is provided, it will
     * return the organization's owner. Otherwise, it returns the authenticated user.
     */
    private function resolveUser(Request $request): ?User
    {
        $authenticatedUser = $request->user();
        $orgId = $request->query('org_id') ?: $request->input('org_id');

        if ($orgId && $authenticatedUser->hasRole('superadmin')) {
            $organization = Organization::find($orgId);
            return $organization?->user;
        }

        return $authenticatedUser;
    }

    /**
     * Format the payload for the current plan response.
     */
    private function formatPlanPayload(Subscription $subscription): array
    {
        $latestInvoice = $subscription->invoices()->first();

        return [
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'start' => $subscription->started_at,
            'end' => $subscription->ends_at,
            'current_period_end' => $subscription->current_period_end,
            'trial_ends_at' => $subscription->trial_ends_at,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'is_paused' => $subscription->is_paused,
            'latest_invoice' => $latestInvoice ? [
                'amount' => $latestInvoice->amount_paid,
                'currency' => $latestInvoice->currency,
                'status' => $latestInvoice->status,
                'paid_at' => $latestInvoice->paid_at,
                'invoice_url' => $latestInvoice->hosted_invoice_url,
            ] : null,
        ];
    }

    /**
     * Format the payload for a billing history item.
     */
    private function formatHistoryPayload(Subscription $subscription): array
    {
        $invoices = $subscription->invoices;

        return [
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'started_at' => $subscription->started_at,
            'ends_at' => $subscription->ends_at,
            'current_period_end' => $subscription->current_period_end,
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'amount_due' => $invoice->amount_due,
                    'amount_paid' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at,
                    'invoice_url' => $invoice->hosted_invoice_url,
                    'stripe_invoice_id' => $invoice->stripe_invoice_id,
                ];
            }),
        ];
    }
}
