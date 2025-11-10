<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    /**
     * Check if a subscription has expired.
     */
    public function hasExpired(Subscription $subscription): bool
    {
        // If no end date is set, consider it as never expiring
        if (! $subscription->ends_at) {
            return false;
        }

        // Compare the end date with the current time
        return $subscription->ends_at->isPast();
    }

    /**
     * Check if a subscription is currently active (not expired).
     */
    public function isActive(Subscription $subscription): bool
    {
        return $subscription->isActive() && ! $this->hasExpired($subscription);
    }

    /**
     * Update subscription status if it has expired and return the status.
     *
     * @return bool True if active, false if expired
     */
    // checkAndUpdateStatus() removed: not referenced. Subscription lifecycle is handled by
    // webhook processing and scheduled jobs; keep helper methods minimal.

    // Get the current active subscription plan for the relevant user.
    // Accessible by the user or by a superadmin viewing a specific organization.

    public function getCurrentPlan(Request $request)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json(null);
        }

        $currentSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->latest('created_at')
            ->first();

        if (! $currentSubscription) {
            return response()->json(null);
        }

        // The formatting logic is now handled by the Subscription model's accessors.
        return response()->json($this->formatPlanPayload($currentSubscription));
    }

    // Get the entire billing history for the relevant user.

    public function getBillingHistory(Request $request)
    {
        $user = $this->resolveUser($request);

        if (! $user) {
            return response()->json([]);
        }

        $history = $user->subscriptions()
            ->latest('created_at')
            ->get()
            ->flatMap(fn ($subscription) => $this->formatHistoryPayload($subscription));

        return response()->json($history);
    }

    // Get a simple subscription status for the relevant user.

    public function subscriptionStatus(Request $request)
    {
        $user = $this->resolveUser($request);

        $subscription = $user?->subscriptions()->latest('created_at')->first();
        $latestInvoice = $subscription?->invoices()->first();
        $org = $user ? Organization::where('user_id', $user->id)->first() : null;
        $plan = $subscription?->plan;

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
            'organization_last_contacted' => $org?->last_contacted?->toDateTimeString(),
            'payment_method' => $subscription?->payment_method_label,
            'payment_method_type' => $subscription?->payment_method_type,
            'payment_method_brand' => $subscription?->payment_method_brand,
            'payment_method_last4' => $subscription?->payment_method_last4,
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'description' => $plan->description,
            ] : null,
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

        // If unauthenticated, we cannot resolve a user
        if (! $authenticatedUser) {
            return null;
        }

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
        $plan = $subscription->plan; // eager access for convenience

        return [
            // Core identifiers
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            // Status / lifecycle
            'status' => $subscription->status,
            'start' => $subscription->started_at,
            'end' => $subscription->ends_at,
            'current_period_end' => $subscription->current_period_end,
            'trial_ends_at' => $subscription->trial_ends_at,
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'is_paused' => $subscription->is_paused,
            // Payment method flattened (for existing consumers)
            'payment_method' => $subscription->payment_method_label,
            'payment_method_type' => $subscription->payment_method_type,
            'payment_method_brand' => $subscription->payment_method_brand,
            'payment_method_last4' => $subscription->payment_method_last4,
            // Nested payment method object (new preferred shape)
            'payment_method_object' => [
                'id' => $subscription->default_payment_method_id,
                'type' => $subscription->payment_method_type,
                'brand' => $subscription->payment_method_brand,
                'last4' => $subscription->payment_method_last4,
                'label' => $subscription->payment_method_label,
            ],
            // Plan details (nested + convenience top-level amount for legacy front-end code)
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'description' => $plan->description,
                'status' => $plan->status,
            ] : null,
            'plan_name' => $plan?->name,
            'plan_amount' => $plan?->amount, // explicit for front-end parsing
            'amount' => $plan?->amount, // legacy alias consumed by existing code
            'plan_interval' => $plan?->interval,
            'plan_currency' => $plan?->currency,
            // Latest invoice information (if exists)
            'latest_invoice' => $latestInvoice ? [
                'id' => $latestInvoice->id,
                'stripe_invoice_id' => $latestInvoice->stripe_invoice_id,
                'amount_due' => $latestInvoice->amount_due,
                'amount' => $latestInvoice->amount_paid, // convenience alias
                'amount_paid' => $latestInvoice->amount_paid,
                'currency' => $latestInvoice->currency,
                'status' => $latestInvoice->status,
                'paid_at' => $latestInvoice->paid_at,
                'invoice_url' => $latestInvoice->hosted_invoice_url,
            ] : null,
        ];
    }

    /**
     * Format the payload for a billing history item.
     * Returns an array of invoice records for the subscription.
     * - If subscription has no invoices: returns array with 1 record (subscription details)
     * - If subscription has invoices: returns array with N records (one per invoice)
     */
    private function formatHistoryPayload(Subscription $subscription): array
    {
        $invoices = $subscription->invoices;
        $plan = $subscription->plan;
        $currency = strtolower($plan->currency ?? 'usd');
        $symbol = match ($currency) {
            'usd' => '$', 'eur' => '€', 'gbp' => '£', default => strtoupper($currency) . ' ',
        };

        // If there are no invoices, return a single record for the subscription
        if ($invoices->isEmpty()) {
            return [
                [
                    'subscription_id' => $subscription->id,
                    'plan_id' => $subscription->plan_id,
                    'status' => $subscription->status,
                    'subscriptionEnd' => $subscription->ends_at?->toDateTimeString(),
                    'paymentDate' => $subscription->started_at?->toDateTimeString(),
                    'payment_method' => $subscription->payment_method_label,
                    'amount' => $plan?->amount,
                    'currency' => $plan?->currency,
                    'pdfUrl' => null,
                    'description' => $plan ? ($plan->name . ' subscription (' . $symbol . $plan->amount . '/' . ($plan->interval ?? '')) : 'Subscription payment',
                ],
            ];
        }

        // Map each invoice to a billing history record
        return $invoices->map(function ($invoice) use ($subscription, $plan, $symbol) {
            return [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'subscriptionEnd' => $subscription->ends_at?->toDateTimeString(),
                'paymentDate' => $invoice->paid_at?->toDateTimeString(),
                'payment_method' => $subscription->payment_method_label,
                'amount' => $invoice->amount_paid,
                'currency' => $invoice->currency,
                'pdfUrl' => $invoice->hosted_invoice_url,
                'description' => $plan ? ($plan->name . ' subscription (' . $symbol . ($invoice->amount_paid ?? $plan->amount) . '/' . ($plan->interval ?? '') . ')') : 'Subscription payment',
                'invoice_id' => $invoice->id,
                'stripe_invoice_id' => $invoice->stripe_invoice_id,
            ];
        })->toArray();
    }
}
