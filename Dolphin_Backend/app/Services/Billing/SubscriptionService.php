<?php

namespace App\Services\Billing;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

class SubscriptionService
{
    /** Return current active subscription for user (latest active). */
    public function current(User $user): ?Subscription
    {
        return $user->subscriptions()->where('status', 'active')->latest('created_at')->first();
    }

    /** Return subscription status payload (matches existing API shape). */
    public function statusPayload(User $user): array
    {
        $subscription = $user->subscriptions()->latest('created_at')->first();
        $latestInvoice = $subscription?->invoices()->first();
        $plan = $subscription?->plan;

        return [
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
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'interval' => $plan->interval,
                'amount' => $plan->amount,
                'currency' => $plan->currency,
                'description' => $plan->description,
            ] : null,
            'plan_name' => $plan?->name,
            'payment_method' => $subscription ? [
                'id' => $subscription->default_payment_method_id,
                'type' => $subscription->payment_method_type,
                'brand' => $subscription->payment_method_brand,
                'last4' => $subscription->payment_method_last4,
                'label' => $subscription->payment_method_label,
            ] : null,
        ];
    }

    /** Billing history collection payload. */
    public function history(User $user): Collection
    {
        return $user->subscriptions()->latest('created_at')->get()->map(function (Subscription $subscription) {
            $plan = $subscription->plan;

            // Map stored invoices
            $invoices = $subscription->invoices->map(function ($invoice) use ($plan) {
                return [
                    'id' => $invoice->id,
                    'amount_due' => $invoice->amount_due,
                    'amount_paid' => $invoice->amount_paid,
                    'currency' => $invoice->currency,
                    'status' => $invoice->status,
                    'paid_at' => $invoice->paid_at,
                    'invoice_url' => $invoice->hosted_invoice_url,
                    'stripe_invoice_id' => $invoice->stripe_invoice_id,
                    // Description is not stored; provide a friendly fallback using plan name when available
                    'description' => $plan ? ('Dolphin ' . $plan->name . ' Plan') : null,
                ];
            });

            // If no invoices recorded yet, synthesize a first entry from subscription & plan so UI has something to show
            if ($invoices->isEmpty()) {
                $invoices = collect([
                    [
                        'id' => null,
                        'amount_due' => $plan?->amount,
                        'amount_paid' => $plan?->amount,
                        'currency' => $plan?->currency ?? 'USD',
                        'status' => $subscription->status ?? 'paid',
                        'paid_at' => $subscription->started_at,
                        'invoice_url' => null,
                        'stripe_invoice_id' => null,
                        'description' => $plan ? ('Dolphin ' . $plan->name . ' Plan') : null,
                    ],
                ]);
            }

            // Payment method label synthesized from subscription fields (avoid nested ternary for readability)
            $paymentMethod = $subscription->payment_method_label;
            if (!$paymentMethod && $subscription->payment_method_brand && $subscription->payment_method_last4) {
                $paymentMethod = ucfirst((string) $subscription->payment_method_brand) . ' ****' . $subscription->payment_method_last4;
            }

            return [
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status,
                'started_at' => $subscription->started_at,
                'ends_at' => $subscription->ends_at,
                'current_period_end' => $subscription->current_period_end,
                'payment_method' => $paymentMethod,
                'invoices' => $invoices,
            ];
        });
    }
}
