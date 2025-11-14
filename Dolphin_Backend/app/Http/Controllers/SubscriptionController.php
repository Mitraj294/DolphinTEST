<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use App\Services\UrlBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    public function createCheckoutSession(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'price_id' => ['required', 'string'],
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ]);

        $plan = Plan::where('stripe_price_id', $validated['price_id'])->firstOrFail();

        $secret = config('services.stripe.secret');
        if (! $secret) {
            Log::error('Stripe secret missing when attempting to create checkout session.');

            return response()->json(['error' => 'Stripe is not configured'], 500);
        }

        Stripe::setApiKey($secret);

        $stripeCustomerId = $this->resolveStripeCustomerId($user);
        $successUrl = $validated['success_url'] ?? UrlBuilder::subscriptionsSuccessUrl();
        $cancelUrl = $validated['cancel_url'] ?? UrlBuilder::subscriptionsCancelledUrl();

        try {
            $session = StripeSession::create([
                'customer' => $stripeCustomerId,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $validated['price_id'],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $user->id,
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                    'price_id' => (string) $validated['price_id'],
                ],
                'subscription_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'plan_id' => (string) $plan->id,
                        'price_id' => (string) $validated['price_id'],
                    ],
                ],
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe checkout session creation failed', [
                'message' => $exception->getMessage(),
                'user_id' => $user->id,
                'price_id' => $validated['price_id'],
            ]);

            return response()->json(['error' => 'Unable to create checkout session'], 422);
        }

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ]);
    }

    public function createCheckoutSessionGuest(Request $request): JsonResponse
    {

        $validated = $request->validate([
            'price_id' => ['required', 'string'],
            'email' => ['nullable', 'email', 'required_without:lead_id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id', 'required_without:email'],
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ]);



        $secret = config('services.stripe.secret');
        if (! $secret) {
            Log::error('Stripe secret missing when attempting to create guest checkout session.');

            return response()->json(['error' => 'Stripe is not configured'], 500);
        }

        Stripe::setApiKey($secret);

        try {
            $guestEmail = $validated['email'] ?? null;
            if (empty($guestEmail) && ! empty($validated['lead_id'])) {
                $lead = Lead::find($validated['lead_id']);
                if ($lead) {
                    $guestEmail = $lead->email;
                }
            }

            if (empty($guestEmail)) {
                Log::warning('Guest checkout attempted without an email and lead did not resolve to an email', ['payload' => $validated]);
                return response()->json(['error' => 'Email or lead_id with an email is required'], 422);
            }

            $customer = Customer::create([
                'email' => $guestEmail,
            ]);

            $successUrl = $validated['success_url'] ?? UrlBuilder::subscriptionsSuccessUrl();
            $cancelUrl = $validated['cancel_url'] ?? UrlBuilder::subscriptionsCancelledUrl();

            $session = StripeSession::create([
                'customer' => $customer->id,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $validated['price_id'],
                    'quantity' => 1,
                ]],
                'mode' => 'subscription',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => isset($validated['lead_id']) ? (string)$validated['lead_id'] : null,
                'metadata' => [
                    'lead_id' => isset($validated['lead_id']) ? (string)$validated['lead_id'] : null,
                    'price_id' => (string)$validated['price_id'],
                    'guest_email' => $guestEmail,
                ],
                'subscription_data' => [
                    'metadata' => [
                        'lead_id' => isset($validated['lead_id']) ? (string)$validated['lead_id'] : null,
                        'price_id' => (string)$validated['price_id'],
                        'guest_email' => $guestEmail,
                    ],
                ],
            ]);
        } catch (ApiErrorException $exception) {
            Log::error('Stripe guest checkout session creation failed', [
                'message' => $exception->getMessage(),
                'email' => $validated['email'],
                'price_id' => $validated['price_id'],
            ]);

            return response()->json(['error' => 'Unable to create checkout session'], 422);
        }

        return response()->json([
            'id' => $session->id,
            'url' => $session->url,
        ]);
    }

    private function resolveStripeCustomerId(User $user): string
    {
        if ($user->stripe_id) {
            return $user->stripe_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: null,
        ]);

        $user->forceFill(['stripe_id' => $customer->id])->save();

        return $customer->id;
    }

    public function hasExpired($subscription): bool
    {
        if (! $subscription) {
            return true;
        }
        if (is_numeric($subscription)) {
            $subscription = \App\Models\Subscription::find($subscription);
            if (! $subscription) {
                return true;
            }
        }
        if (method_exists($subscription, 'isActive')) {
            return ! $subscription->isActive();
        }
        if (isset($subscription->status) && $subscription->status === 'expired') {
            return true;
        }
        if (isset($subscription->ends_at) && $subscription->ends_at) {
            try {
                return \Carbon\Carbon::parse($subscription->ends_at)->isPast();
            } catch (\Throwable $e) {
                return true;
            }
        }
        return false;
    }
}
