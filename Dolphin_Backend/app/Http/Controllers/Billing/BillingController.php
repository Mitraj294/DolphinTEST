<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BillingController extends Controller
{
    
    public function current(Request $request)
    {
        
        
        
        $user = $request->user();
        
        
        
        
        
        
        if (
            ! $user ||
            ! method_exists($user, 'hasRole') ||
            (! $user->hasRole('organizationadmin') && ! $user->hasRole('superadmin'))
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        try {
            $subscription = $this->resolveCurrentSubscription($request);
            if (! $subscription) {
                return response()->json(null);
            }

            $subscription->loadMissing(['plan', 'invoices']);
            $latestInvoice = $subscription->invoices->first();
            $plan = $subscription->plan;

            return response()->json([
                'plan_id' => $subscription->plan_id,
                'plan' => $plan ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'interval' => $plan->interval,
                    'amount' => $plan->amount,
                    'currency' => $plan->currency,
                    'description' => $plan->description,
                ] : null,
                'plan_name' => $plan?->name,
                'status' => $subscription->status,
                'start' => $subscription->started_at,
                'end' => $subscription->ends_at,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
                'cancel_at_period_end' => $subscription->cancel_at_period_end,
                'is_paused' => $subscription->is_paused,
                'latest_invoice' => $latestInvoice ? [
                    'amount' => $latestInvoice->amount_paid ?? null,
                    'currency' => $latestInvoice->currency ?? null,
                    'status' => $latestInvoice->status ?? null,
                    'paid_at' => $latestInvoice->paid_at ?? null,
                    'invoice_url' => $latestInvoice->hosted_invoice_url ?? null,
                ] : null,
                'payment_method' => [
                    'id' => $subscription->default_payment_method_id,
                    'type' => $subscription->payment_method_type,
                    'brand' => $subscription->payment_method_brand,
                    'last4' => $subscription->payment_method_last4,
                    'label' => $subscription->payment_method_label,
                ],
            ]);
        } catch (\Throwable $e) {
            
            return response()->json(null);
        }
    }

    
    public function status(Request $request)
    {
        
        $user = $request->user();
        if (
            ! $user ||
            ! method_exists($user, 'hasRole') ||
            (! $user->hasRole('organizationadmin') && ! $user->hasRole('superadmin'))
        ) {
            return response()->json(['status' => 'none', 'message' => 'Unauthorized.'], 403);
        }
        try {
            $subscription = $this->resolveLatestSubscription($request);
            if (! $subscription) {
                return response()->json(['status' => 'none']);
            }

            $latestInvoice = $subscription->invoices()->first();
            $plan = $subscription->plan;

            return response()->json([
                'status' => $subscription->status ?? 'none',
                'plan_id' => $subscription->plan_id,
                'subscription_id' => $subscription->id,
                'started_at' => $subscription->started_at?->toDateTimeString(),
                'ends_at' => $subscription->ends_at?->toDateTimeString(),
                'current_period_end' => $subscription->current_period_end?->toDateTimeString(),
                'is_paused' => $subscription->is_paused ?? false,
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
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
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'none']);
        }
    }

    
    public function history(Request $request)
    {
        
        $user = $request->user();
        if (
            ! $user ||
            ! method_exists($user, 'hasRole') ||
            (! $user->hasRole('organizationadmin') && ! $user->hasRole('superadmin'))
        ) {
            return response()->json([], 403);
        }
        try {
            $user = $this->resolveUser($request);

            
            try {
                Log::info('BillingController::history - resolved user', [
                    'authenticated_user_id' => $request->user()?->id ?? null,
                    'resolved_user_id' => $user?->id ?? null,
                    'org_id_param' => $request->query('org_id') ?: $request->input('org_id'),
                ]);
            } catch (\Throwable $_e) {
                Log::warning('BillingController::history - failed to emit resolved-user log', [
                    'error' => $_e->getMessage(),
                ]);
            }
            if (! $user) {
                return response()->json([]);
            }

            $subscriptions = $user->subscriptions()->with(['plan', 'invoices'])->latest('created_at')->get();

            
            try {
                $ids = $subscriptions->pluck('id')->toArray();
                Log::info('BillingController::history - subscriptions found', [
                    'resolved_user_id' => $user->id,
                    'count' => $subscriptions->count(),
                    'subscription_ids' => $ids,
                ]);
            } catch (\Throwable $_e) {
                Log::warning('BillingController::history - failed to emit subscriptions-found log', [
                    'error' => $_e->getMessage(),
                ]);
            }

            
            
            
            
            
            
            $history = $subscriptions->flatMap([$this, 'formatHistoryPayload'])->toArray();

            return response()->json($history);
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    
    private function resolveUser(Request $request): ?\App\Models\User
    {
        
        $authenticatedUser = $request->user();
        if (!$authenticatedUser) {
            return null;
        }
        
        $orgId = $request->query('org_id') ?: $request->input('org_id');
        if ($orgId && method_exists($authenticatedUser, 'hasRole') && $authenticatedUser->hasRole('superadmin')) {
            $organization = Organization::find($orgId);
            return $organization?->user;
        }

        
        
        
        
        if (method_exists($authenticatedUser, 'hasRole') && $authenticatedUser->hasRole('organizationadmin')) {
            
            $userOrgId = $authenticatedUser->organization_id ?? null;
            if ($userOrgId) {
                $organization = Organization::find($userOrgId);
                if ($organization && $organization->user) {
                    return $organization->user;
                }
            }

            
            
            
            try {
                $memberOrg = $authenticatedUser->organizationMemberships()->first();
                if ($memberOrg && $memberOrg->user) {
                    return $memberOrg->user;
                }
            } catch (\Throwable $e) {
                Log::warning('BillingController::resolveUser - failed to resolve organization membership', [
                    'authenticated_user_id' => $authenticatedUser->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $authenticatedUser;
    }

    
    private function resolveCurrentSubscription(Request $request): ?Subscription
    {
        
        $user = $this->resolveUser($request);
        if (! $user) {
            return null;
        }

        return $user->subscriptions()
            ->with(['plan', 'invoices'])
            ->where('status', 'active')
            ->latest('created_at')
            ->first()
            ?? $user->subscriptions()->with(['plan', 'invoices'])->latest('created_at')->first();
    }

    
    private function resolveLatestSubscription(Request $request): ?Subscription
    {
        
        $user = $this->resolveUser($request);
        if (! $user) {
            return null;
        }

        return $user->subscriptions()->with(['plan', 'invoices'])->latest('created_at')->first();
    }

    
    private function formatHistoryPayload(Subscription $subscription): array
    {
        
        $invoices = $subscription->invoices instanceof Collection
            ? $subscription->invoices
            : Collection::wrap($subscription->invoices ?? []);
        $plan = $subscription->plan;
        $symbol = $this->resolveCurrencySymbol($plan?->currency);

        if ($invoices->isEmpty()) {
            return [$this->formatSubscriptionSummary($subscription, $plan, $symbol)];
        }

        $history = [];
        foreach ($invoices as $invoice) {
            $history[] = $this->formatInvoicePayload($invoice, $subscription, $plan, $symbol);
        }

        return $history;
    }

    
    private function formatInvoicePayload($invoice, Subscription $subscription, $plan, string $symbol): array
    {
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
    }

    
    private function formatSubscriptionSummary(Subscription $subscription, $plan, string $symbol): array
    {
        return [
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'status' => $subscription->status,
            'subscriptionEnd' => $subscription->ends_at?->toDateTimeString(),
            'paymentDate' => $subscription->started_at?->toDateTimeString(),
            'payment_method' => $subscription->payment_method_label,
            'amount' => $plan?->amount,
            'currency' => $plan?->currency,
            'pdfUrl' => null,
            'description' => $plan
                ? ($plan->name . ' subscription (' . $symbol . $plan->amount . '/' . ($plan->interval ?? '') . ')')
                : 'Subscription payment',
        ];
    }

    private function resolveCurrencySymbol(?string $currency): string
    {
        $code = strtolower($currency ?? 'usd');

        return match ($code) {
            'usd' => '$',
            'eur' => 'EUR ',
            'gbp' => 'GBP ',
            default => strtoupper($code) . ' ',
        };
    }
}
