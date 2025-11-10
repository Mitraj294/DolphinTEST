<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class SubscriptionController extends Controller
{
    /**
     * Create a Stripe Checkout session for the authenticated user.
     */
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
        $successUrl = $validated['success_url'] ?? $this->defaultSuccessUrl();
        $cancelUrl = $validated['cancel_url'] ?? $this->defaultCancelUrl();

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
                    'plan_id' => (string) $plan->id,
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

    private function defaultSuccessUrl(): string
    {
        $base = $this->frontendBaseUrl();

        return $base . '/subscriptions/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function defaultCancelUrl(): string
    {
        $base = $this->frontendBaseUrl();

        return $base . '/subscriptions/cancelled';
    }

    private function frontendBaseUrl(): string
    {
        $base = config('app.frontend_url')
            ?? env('FRONTEND_URL')
            ?? config('app.url')
            ?? 'http://localhost:8080';

        return rtrim($base, '/');
    }
}
