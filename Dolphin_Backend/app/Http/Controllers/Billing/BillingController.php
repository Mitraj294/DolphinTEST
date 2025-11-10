<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Billing\SubscriptionService;
use App\Models\Organization;

class BillingController extends Controller
{
    public function __construct(private SubscriptionService $service)
    {
    }

    /** GET /api/subscription */
    public function current(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(null);
            }

            $sub = $this->service->current($user);
            if (!$sub) {
                return response()->json(null);
            }

            $latestInvoice = null;
            try {
                $latestInvoice = $sub->invoices()->first();
            } catch (\Throwable $e) {
                // ignore invoice retrieval issues
            }

            $plan = null;
            if (isset($sub->plan) && $sub->plan) {
                $plan = [
                    'id' => $sub->plan->id,
                    'name' => $sub->plan->name,
                    'interval' => $sub->plan->interval,
                    'amount' => $sub->plan->amount,
                    'currency' => $sub->plan->currency,
                    'description' => $sub->plan->description,
                ];
            }

            return response()->json([
                'plan_id' => $sub->plan_id ?? null,
                'plan' => $plan,
                'plan_name' => $sub->plan->name ?? null,
                'status' => $sub->status ?? null,
                'start' => $sub->started_at ?? null,
                'end' => $sub->ends_at ?? null,
                'current_period_end' => $sub->current_period_end ?? null,
                'trial_ends_at' => $sub->trial_ends_at ?? null,
                'cancel_at_period_end' => $sub->cancel_at_period_end ?? null,
                'is_paused' => $sub->is_paused ?? false,
                'latest_invoice' => $latestInvoice ? [
                    'amount' => $latestInvoice->amount_paid ?? null,
                    'currency' => $latestInvoice->currency ?? null,
                    'status' => $latestInvoice->status ?? null,
                    'paid_at' => $latestInvoice->paid_at ?? null,
                    'invoice_url' => $latestInvoice->hosted_invoice_url ?? null,
                ] : null,
                'payment_method' => [
                    'id' => $sub->default_payment_method_id ?? null,
                    'type' => $sub->payment_method_type ?? null,
                    'brand' => $sub->payment_method_brand ?? null,
                    'last4' => $sub->payment_method_last4 ?? null,
                    'label' => $sub->payment_method_label ?? null,
                ],
            ]);
        } catch (\Throwable $e) {
            // defensive fallback
            return response()->json(null);
        }
    }

    /** GET /api/subscription/status */
    public function status(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['status' => 'none']);
            }
            return response()->json($this->service->statusPayload($user));
        } catch (\Throwable $e) {
            return response()->json(['status' => 'none']);
        }
    }

    /** GET /api/billing/history */
    public function history(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json([]);
            }
            return response()->json($this->service->history($user));
        } catch (\Throwable $e) {
            return response()->json([]);
        }
    }

    /**
     * Resolve the user for the request.
     * If the requester is a superadmin and an org_id is provided, it will
     * return the organization's owner. Otherwise, it returns the authenticated user.
     */
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
        return $authenticatedUser;
    }
}
