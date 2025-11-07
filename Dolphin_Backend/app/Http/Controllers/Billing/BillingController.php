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
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(null);
        }
        $sub = $this->service->current($user);
        if (!$sub) {
            return response()->json(null);
        }
        $latestInvoice = $sub->invoices()->first();
        return response()->json([
            'plan_id' => $sub->plan_id,
            'plan' => $sub->plan ? [
                'id' => $sub->plan->id,
                'name' => $sub->plan->name,
                'interval' => $sub->plan->interval,
                'amount' => $sub->plan->amount,
                'currency' => $sub->plan->currency,
                'description' => $sub->plan->description,
            ] : null,
            'plan_name' => $sub->plan->name ?? null,
            'status' => $sub->status,
            'start' => $sub->started_at,
            'end' => $sub->ends_at,
            'current_period_end' => $sub->current_period_end,
            'trial_ends_at' => $sub->trial_ends_at,
            'cancel_at_period_end' => $sub->cancel_at_period_end,
            'is_paused' => $sub->is_paused,
            'latest_invoice' => $latestInvoice ? [
                'amount' => $latestInvoice->amount_paid,
                'currency' => $latestInvoice->currency,
                'status' => $latestInvoice->status,
                'paid_at' => $latestInvoice->paid_at,
                'invoice_url' => $latestInvoice->hosted_invoice_url,
            ] : null,
            'payment_method' => [
                'id' => $sub->default_payment_method_id,
                'type' => $sub->payment_method_type,
                'brand' => $sub->payment_method_brand,
                'last4' => $sub->payment_method_last4,
                'label' => $sub->payment_method_label,
            ],
        ]);
    }

    /** GET /api/subscription/status */
    public function status(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['status' => 'none']);
        }
        return response()->json($this->service->statusPayload($user));
    }

    /** GET /api/billing/history */
    public function history(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json([]);
        }
        return response()->json($this->service->history($user));
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
