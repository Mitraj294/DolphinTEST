<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Invoice;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;
use Throwable;
use Carbon\Carbon;

class StripeSubscriptionController extends Controller
{

    public function createCheckoutSession(Request $request)
    {
        Log::info('createCheckoutSession request:', $request->all());

        // Attempt optional token auth if an Authorization header is present.
        $optionalAuthUser = $this->attemptOptionalTokenAuth($request);

        $user = $optionalAuthUser ?: Auth::user();
        $priceId = $request->input('price_id');
        $frontend = env('FRONTEND_URL', 'http://127.0.0.1:8080');

        $response = [];
        $status = 200;

        if (!$priceId) {
            $response = ['error' => 'Missing price_id'];
            $status = 400;
        } else {
            // Validate guest inputs (when there's no authenticated user) and obtain customer email / lead id
            $validation = $this->validateGuestAndEmail($request, $user);
            if (!empty($validation['error'])) {
                $response = ['error' => $validation['error']];
                $status = $validation['status'] ?? 400;
            } else {
                $customerEmail = $validation['customerEmail'] ?? null;
                $leadId = $validation['leadId'] ?? null;

                // Build the success URL used by Stripe to redirect after checkout
                $successUrl = $this->buildSuccessUrl($frontend, $customerEmail, $leadId, $request);
                Log::info('Success URL:', ['url' => $successUrl]);

                Stripe::setApiKey(config('services.stripe.secret'));

                try {
                    $session = StripeSession::create([
                        'payment_method_types' => ['card'],
                        'mode' => 'subscription',
                        'customer_email' => $customerEmail,
                        'line_items' => [[
                            'price' => $priceId,
                            'quantity' => 1,
                        ]],
                        'success_url' => $successUrl,
                        'cancel_url' => $frontend . '/subscriptions/plans',
                    ]);

                    $response = ['id' => $session->id, 'url' => $session->url];
                    $status = 200;
                } catch (\Stripe\Exception\InvalidRequestException $e) {
                    Log::error('Stripe InvalidRequestException creating checkout session', [
                        'message' => $e->getMessage(),
                        'params' => ['price_id' => $priceId, 'email' => $customerEmail, 'lead_id' => $leadId]
                    ]);
                    $response = ['error' => 'Stripe rejected the request: ' . $e->getMessage()];
                    $status = 400;
                } catch (\Throwable $e) {
                    Log::error('Unexpected error creating Stripe checkout session: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                    $response = ['error' => 'Could not create Stripe checkout session.'];
                    $status = 500;
                }
            }
        }

        return response()->json($response, $status);
    }

    /**
     * Attempt optional token authentication from Authorization header and set the auth user.
     * Returns the resolved User or null.
     */
    private function attemptOptionalTokenAuth(Request $request): ?User
    {
        $found = null;
        try {
            $authHeader = $request->header('Authorization');
            if (!$authHeader || !preg_match('/Bearer\\s+(.*)$/i', $authHeader, $m)) {
                return null;
            }

            $bearer = $m[1];

            // Try three strategies to locate an access token id: pipe-prefixed id, JWT jti, or direct id.
            $tokenId = $this->getTokenIdFromBearer($bearer);
            if ($tokenId) {
                $accessTokenModel = DB::table('oauth_access_tokens')->where('id', $tokenId)->first();
            } else {
                $accessTokenModel = DB::table('oauth_access_tokens')->where('id', $bearer)->first();
            }

            if ($accessTokenModel) {
                $now = Carbon::now();
                $expiresAt = isset($accessTokenModel->expires_at) ? Carbon::parse($accessTokenModel->expires_at) : null;
                if (empty($accessTokenModel->revoked) && (!$expiresAt || $expiresAt->gt($now))) {
                    $authUser = User::find($accessTokenModel->user_id);
                    if ($authUser) {
                        Auth::setUser($authUser);
                        $found = $authUser;
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Optional token authentication failed: ' . $e->getMessage());
        }
        return $found;
    }

    /**
     * Extract a likely token id from a bearer string.
     * Returns an id string when one of the heuristics matches, or null otherwise.
     */
    private function getTokenIdFromBearer(string $bearer): ?string
    {
        // 'id|...' format used by some tokens
        if (strpos($bearer, '|') !== false) {
            [$idPart] = explode('|', $bearer, 2);
            return $idPart;
        }

        // JWT-like token: attempt to decode the payload and extract jti
        if (substr_count($bearer, '.') === 2) {
            try {
                $parts = explode('.', $bearer);
                $payloadB64 = $parts[1] ?? null;
                $payloadJson = $payloadB64 ? base64_decode(strtr($payloadB64, '-_', '+/')) : null;
                $payload = json_decode($payloadJson, true);
                if (is_array($payload) && !empty($payload['jti'])) {
                    return $payload['jti'];
                }
            } catch (\Exception $e) {
                // ignore malformed JWTs
            }
        }

        return null;
    }

    /**
     * Validate guest inputs (email and lead_id) when no authenticated user exists.
     * Returns array with keys: customerEmail, leadId, error, status.
     */
    private function validateGuestAndEmail(Request $request, ?User $user): array
    {
        $result = ['customerEmail' => null, 'leadId' => null, 'error' => null, 'status' => 200];

        if ($user) {
            $result['customerEmail'] = $user->email;
        } else {
            $customerEmail = trim((string)$request->input('email')) ?: null;
            $leadId = $request->input('lead_id');

            if (!$customerEmail || !$leadId) {
                $result['error'] = 'Email and lead_id are required for guest checkout';
                $result['status'] = 400;
            } else {
                $lower = strtolower($customerEmail);
                if (in_array($lower, ['...', 'n/a', 'na', 'none', 'null', 'user@example.com'], true) || strlen($customerEmail) > 254) {
                    Log::warning('Guest email rejected as placeholder or too long', ['email' => $customerEmail, 'lead_id' => $leadId]);
                    $result['error'] = 'Invalid email address provided';
                    $result['status'] = 400;
                } elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    Log::warning('Invalid guest email provided for checkout', ['email' => $customerEmail, 'lead_id' => $leadId]);
                    $result['error'] = 'Invalid email address provided';
                    $result['status'] = 400;
                } else {
                    Log::info('Guest checkout email validated', ['email' => $customerEmail, 'lead_id' => $leadId]);
                    $result['customerEmail'] = $customerEmail;
                    $result['leadId'] = $leadId;
                }
            }
        }

        return $result;
    }

    /**
     * Build the success URL with optional guest parameters.
     */
    private function buildSuccessUrl(string $frontend, ?string $customerEmail, $leadId, Request $request): string
    {
        $successUrl = $frontend . '/subscriptions/success?checkout_session_id={CHECKOUT_SESSION_ID}';
        if ($customerEmail && $leadId) {
            $successUrl .= '&email=' . urlencode($customerEmail) . '&lead_id=' . $leadId;
        }

        $guestCode = $request->input('guest_code');
        $guestToken = $request->input('guest_token');
        if ($guestCode) {
            $successUrl .= '&guest_code=' . urlencode($guestCode);
        } elseif ($guestToken) {
            // Backwards compatibility: some emails still include the full personal access token
            $successUrl .= '&guest_token=' . urlencode($guestToken);
        }

        return $successUrl;
    }


    /**
     * Refresh the authenticated user's roles and return them.
     * This endpoint is intended to be called by the frontend after a
     * successful subscription checkout so the UI can immediately reflect
     * the new 'organizationadmin' role.
     */
    public function refreshRole(Request $request)
    {
        $response = null;
        $status = 200;

        $user = Auth::user();
        if (!$user) {
            $response = ['error' => 'Unauthenticated'];
            $status = 401;
        } else {
            try {
                $fresh = User::with('roles')->find($user->id);

                if ($fresh) {
                    Auth::guard()->setUser($fresh);

                    // Optionally regenerate session in stateful setups
                    $request->session()?->regenerate();

                    $response = ['roles' => $fresh->roles->pluck('name')];
                } else {
                    $response = ['roles' => []];
                }
            } catch (Throwable $e) {
                Log::warning(
                    'Failed to refresh user roles: ' . $e->getMessage(),
                    ['user_id' => $user->id]
                );
                $response = ['error' => 'Could not refresh roles'];
                $status = 500;
            }
        }

        return response()->json($response, $status);
    }



    public function createCustomerPortal(Request $request)
    {
        $user = Auth::user();
        Stripe::setApiKey(config('services.stripe.secret'));
        $customers = Customer::all(['email' => $user->email, 'limit' => 1]);
        if (count($customers->data) === 0) {
            return response()->json(['error' => 'No Stripe customer found'], 404);
        }
        $customer = $customers->data[0];
        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $customer->id,
            'return_url' => URL::to('/dashboard'),
        ]);
        return response()->json(['url' => $session->url]);
    }

    /**
     * Handles incoming Stripe webhooks with robust error handling and delegation.
     */
    public function handleWebhook(Request $request)
    {
        $responseMessage = 'Webhook handled';
        $responseCode = 200;
        $event = null;

        try {
            $event = $this->verifyAndConstructEvent($request);
            Log::info('Stripe Webhook Event Received:', [
                'type' => $event->type,
                'id' => $event->id
            ]);
        } catch (UnexpectedValueException $e) {
            Log::error('Stripe Webhook Invalid Payload', [
                'error' => $e->getMessage(),
                'payload' => $request->getContent()
            ]);
            $responseMessage = 'Invalid payload';
            $responseCode = 400;
        } catch (SignatureVerificationException $e) {
            Log::error('Stripe Webhook Invalid Signature', [
                'error' => $e->getMessage(),
                'signature' => $request->header('Stripe-Signature')
            ]);
            $responseMessage = 'Invalid signature';
            $responseCode = 400;
        }

        if ($event) {
            try {
                match ($event->type) {
                    'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                    'invoice.paid' => $this->handleInvoicePaid($event->data->object),
                    default => Log::info('Unhandled event type', ['type' => $event->type]),
                };
            } catch (Throwable $e) {
                Log::error('Error processing webhook event', [
                    'event_id' => $event->id,
                    'event_type' => $event->type,
                    'exception' => $e
                ]);
                $responseMessage = 'Webhook handled with internal error';
            }
        }

        return response($responseMessage, $responseCode);
    }


    /**
     * Verifies webhook signature and constructs the Stripe Event object.
     */
    private function verifyAndConstructEvent(Request $request): Event
    {
        return \Stripe\Webhook::constructEvent(
            $request->getContent(),
            $request->header('Stripe-Signature'),
            config('services.stripe.webhook_secret')
        );
    }

    /**
     * Processes the 'checkout.session.completed' event.
     */
    private function handleCheckoutSessionCompleted(StripeSession $session): void
    {
        Log::info('Checkout Session Completed Payload:', (array) $session);
        Stripe::setApiKey(config('services.stripe.secret'));

        $user = User::where('email', $session->customer_email)->first();
        if (!$user) {
            Log::warning('User not found for checkout session', ['email' => $session->customer_email]);
            return;
        }

        $stripeSub = \Stripe\Subscription::retrieve($session->subscription);
        $customer = Customer::retrieve($session->customer);

        // Try to fetch the latest invoice for this subscription (if available)
        $latestInvoice = null;
        try {
            if (!empty($stripeSub->latest_invoice)) {
                $latestInvoice = \Stripe\Invoice::retrieve($stripeSub->latest_invoice);
            }
        } catch (Throwable $e) {
            Log::warning('Unable to retrieve latest invoice for subscription: ' . $e->getMessage(), ['sub_id' => $stripeSub->id]);
            $latestInvoice = null;
        }

        // Normalize start/end using Carbon. If Stripe provides equal/missing values,
        // compute end based on the payment amount (monthly/yearly heuristic).
        $start = $stripeSub->current_period_start ? Carbon::createFromTimestamp($stripeSub->current_period_start) : Carbon::now();
        $end = $stripeSub->current_period_end ? Carbon::createFromTimestamp($stripeSub->current_period_end) : null;

        $amount = $session->amount_total ? $session->amount_total / 100 : null;
        if (is_null($end) || $start->equalTo($end)) {
            if ($amount == 2500) {
                $end = $start->copy()->addYear();
            } else {
                $end = $start->copy()->addMonth();
            }
        }

        $subscriptionData = [
            'user_id' => $user->id,
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id' => $session->customer,
            'plan' => $stripeSub->items->data[0]->plan->id ?? null,
            'status' => $stripeSub->status ?? 'active',
            'payment_date' => now(),
            'subscription_start' => $start instanceof Carbon ? $start->toDateTimeString() : (string)$start,
            'subscription_end' => $end instanceof Carbon ? $end->toDateTimeString() : (string)$end,
            'amount' => $amount,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_country' => $customer->address->country ?? null,
            // Invoice-related fields may not be present on the session; populate from latest invoice when possible
            'receipt_url' => $latestInvoice->hosted_invoice_url ?? null,
            'invoice_number' => $latestInvoice->number ?? null,
            'description' => $latestInvoice->lines->data[0]->description ?? null,
        ];

        $paymentMethodDetails = $this->getPaymentMethodDetailsFromSession($session, $stripeSub);
        $this->updateOrCreateSubscription(array_merge($subscriptionData, $paymentMethodDetails));
    }

    /**
     * Processes the 'invoice.paid' event with fallback logic.
     */
    private function handleInvoicePaid(Invoice $invoice): void
    {
        Log::info('Invoice Paid Payload:', (array) $invoice);
        Stripe::setApiKey(config('services.stripe.secret'));

        $stripeSubscriptionId = $this->getSubscriptionIdFromInvoice($invoice);
        if (!$stripeSubscriptionId) {
            Log::info('Invoice.paid event without a subscription ID, skipping.', ['invoice_id' => $invoice->id]);
            return;
        }

        $customer = Customer::retrieve($invoice->customer);
        $user = User::where('email', $customer->email)->first();
        if (!$user) {
            Log::warning('User not found for invoice', ['email' => $customer->email]);
            return;
        }

        $stripeSub = \Stripe\Subscription::retrieve($stripeSubscriptionId);
        $amount = $invoice->amount_paid / 100;

        // Fallback for subscription period if not available on the subscription object
        // Use Carbon to compute start/end consistently. If Stripe didn't provide
        // a period end (or start and end are equal), compute end based on plan.
        $start = $stripeSub->current_period_start ? Carbon::createFromTimestamp($stripeSub->current_period_start) : Carbon::now();
        $end = $stripeSub->current_period_end ? Carbon::createFromTimestamp($stripeSub->current_period_end) : null;

        if (is_null($end) || $start->equalTo($end)) {
            // Determine period from amount: 250 -> monthly, 2500 -> yearly. Fallback to monthly.
            if ($amount == 2500) {
                $end = $start->copy()->addYear();
            } else {
                $end = $start->copy()->addMonth();
            }
        }

        $subscriptionData = [
            'user_id' => $user->id,
            'stripe_subscription_id' => $stripeSubscriptionId,
            'stripe_customer_id' => $invoice->customer,
            'plan' => $stripeSub->items->data[0]->plan->id ?? null,
            'status' => $stripeSub->status ?? null,
            'payment_date' => date('Y-m-d H:i:s', $invoice->status_transitions->paid_at),
            'subscription_start' => $start instanceof Carbon ? $start->toDateTimeString() : (string)$start,
            'subscription_end' => $end instanceof Carbon ? $end->toDateTimeString() : (string)$end,
            'amount' => $amount,
            'receipt_url' => $invoice->hosted_invoice_url,
            'invoice_number' => $invoice->number,
            'description' => $invoice->lines->data[0]->description ?? null,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_country' => $customer->address->country ?? null,
        ];

        $paymentMethodDetails = $this->getPaymentMethodDetailsFromInvoice($invoice, $customer, $stripeSub);
        $this->updateOrCreateSubscription(array_merge($subscriptionData, $paymentMethodDetails));
    }

    /**
     * Finds subscription ID from invoice, checking lines as a fallback.
     */
    private function getSubscriptionIdFromInvoice(Invoice $invoice): ?string
    {
        if (!empty($invoice->subscription)) {
            return $invoice->subscription;
        }

        Log::info('Subscription ID missing from invoice top-level, checking line items.', ['invoice_id' => $invoice->id]);
        if (isset($invoice->lines->data) && is_array($invoice->lines->data)) {
            foreach ($invoice->lines->data as $line) {
                if (isset($line->subscription) && !empty($line->subscription)) {
                    return $line->subscription;
                }
            }
        }
        return null;
    }

    /**
     * Retrieves payment method details from a checkout session or its related subscription.
     */
    private function getPaymentMethodDetailsFromSession(StripeSession $session, \Stripe\Subscription $stripeSub): array
    {
        try {
            if ($session->payment_intent) {
                $intent = PaymentIntent::retrieve($session->payment_intent);
                if ($intent->payment_method) {
                    $pm = PaymentMethod::retrieve($intent->payment_method);
                    return $this->formatPaymentMethodDetails($pm);
                }
            }

            if ($stripeSub->default_payment_method) {
                Log::info('Payment method extracted from subscription default.', ['sub_id' => $stripeSub->id]);
                $pm = PaymentMethod::retrieve($stripeSub->default_payment_method);
                return $this->formatPaymentMethodDetails($pm);
            }
        } catch (Throwable $e) {
            Log::error('Stripe API error retrieving payment method for checkout session: ' . $e->getMessage(), ['session_id' => $session->id]);
        }
        return [];
    }

    /**
     * Retrieves payment method details from an invoice, customer, or subscription.
     */
    private function getPaymentMethodDetailsFromInvoice(
        Invoice $invoice,
        Customer $customer,
        \Stripe\Subscription $stripeSub
    ): array {
        $pm = null;

        try {
            if ($invoice->payment_intent) {
                $intent = PaymentIntent::retrieve($invoice->payment_intent);
                if ($intent->payment_method) {
                    $pm = PaymentMethod::retrieve($intent->payment_method);
                }
            }

            if (!$pm && $customer->invoice_settings->default_payment_method) {
                Log::info('Attempting fallback for payment method via customer default.', [
                    'customer_id' => $customer->id
                ]);
                $pm = PaymentMethod::retrieve($customer->invoice_settings->default_payment_method);
            }

            if (!$pm && $stripeSub->default_payment_method) {
                Log::info('Attempting fallback for payment method via subscription default.', [
                    'sub_id' => $stripeSub->id
                ]);
                $pm = PaymentMethod::retrieve($stripeSub->default_payment_method);
            }
        } catch (Throwable $e) {
            Log::error('Stripe API error retrieving payment method for invoice: ' . $e->getMessage(), [
                'invoice_id' => $invoice->id
            ]);
        }

        return $pm ? $this->formatPaymentMethodDetails($pm) : [];
    }


    /**
     * Formats a PaymentMethod object into a structured array for the database.
     */
    private function formatPaymentMethodDetails(PaymentMethod $pm): array
    {
        $details = [
            'default_payment_method_id' => $pm->id,
            'payment_method_type' => $pm->type,
            'payment_method_brand' => null,
            'payment_method_last4' => null,
            'payment_method' => ucfirst($pm->type),
        ];

        if ($pm->type === 'card' && $pm->card) {
            $details['payment_method_brand'] = $pm->card->brand;
            $details['payment_method_last4'] = $pm->card->last4;
            $details['payment_method'] = ucfirst($pm->card->brand) . ' ****' . $pm->card->last4;
        }

        Log::info('Formatted payment method details', ['payment_method' => $details['payment_method']]);
        return $details;
    }

    /**
     * Creates/updates a subscription, assigns roles safely, and ensures an organization exists.
     */
    private function updateOrCreateSubscription(array $data): void
    {
        $user = User::find($data['user_id']);
        if (!$user) {
            return;
        }

        // Ensure the user has only the 'organizationadmin' role after subscription.
        // This replaces any previous roles so the latest role is the single active one.
        $orgAdminRole = Role::firstOrCreate(['name' => 'organizationadmin']);
        $user->roles()->sync([$orgAdminRole->id]);

        // If this is the currently authenticated user, reload their roles into the session
        try {
            if (Auth::check() && Auth::id() === $user->id) {
                // Reload fresh user instance from DB and replace the authenticated user
                $fresh = User::with('roles')->find($user->id);
                if ($fresh) {
                    // For Laravel's default auth guard, set the user instance on the guard
                    Auth::guard()->setUser($fresh);
                    // Regenerate session to ensure updated auth payload (optional but helps some setups)
                    request()->session()?->regenerate();
                    Log::info('Refreshed authenticated user after role update', ['user_id' => $user->id]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Failed to refresh auth user after role update: ' . $e->getMessage(), ['user_id' => $user->id]);
        }

        // Create an organization for the user if one doesn't exist
        $organization = Organization::where('user_id', $user->id)->first();
        if (!$organization) {
            Log::info('Creating organization for user subscription', ['user_id' => $user->id]);
            $organization = Organization::create(['user_id' => $user->id]);
            Log::info('Organization created', ['organization_id' => $organization->id, 'user_id' => $user->id]);
        }

        // Update or create the subscription
        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $data['stripe_subscription_id']],
            $data
        );

        Log::info('Subscription record updated/created successfully.', ['subscription_id' => $data['stripe_subscription_id']]);

        // Queue a receipt email to the customer when we have a receipt URL or invoice number
        try {
            if (!empty($data['customer_email']) && filter_var($data['customer_email'], FILTER_VALIDATE_EMAIL)) {
                // Use a lightweight array payload to avoid serializing large objects
                $mailPayload = [
                    'plan' => $data['plan'] ?? $data['plan_name'] ?? null,
                    'plan_name' => $data['plan_name'] ?? null,
                    'amount' => $data['amount'] ?? null,
                    'invoice_number' => $data['invoice_number'] ?? $data['invoiceNumber'] ?? null,
                    'payment_date' => $data['payment_date'] ?? null,
                    'next_billing' => $data['next_billing'] ?? $data['nextBill'] ?? $data['subscription_end'] ?? null,
                    'subscription_end' => $data['subscription_end'] ?? $data['subscriptionEnd'] ?? null,
                    'receipt_url' => $data['receipt_url'] ?? $data['pdfUrl'] ?? null,
                    'customer_name' => $data['customer_name'] ?? null,
                ];

                // Use Notification so we can leverage Notification channels and queuing
                \Illuminate\Support\Facades\Notification::route('mail', $data['customer_email'])
                    ->notify(new \App\Notifications\SubscriptionReceiptNotification($mailPayload));
                Log::info('Queued subscription receipt notification', ['email' => $data['customer_email']]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to queue subscription receipt email: ' . $e->getMessage(), ['user_id' => $user->id]);
        }
    }

    /**
     * Public endpoint to retrieve checkout session and subscription details.
     * Accepts query param `session_id` and returns a compact JSON object with
     * plan, amount, period, subscription_end, next_billing, and invoice info when available.
     */
    public function getSessionDetails(Request $request)
    {
        $sessionId = $request->query('session_id') ?: $request->query('checkout_session_id');
        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            $session = StripeSession::retrieve($sessionId);
        } catch (Throwable $e) {
            Log::warning('Could not retrieve checkout session: ' . $e->getMessage(), ['session_id' => $sessionId]);
            return response()->json(['error' => 'Could not retrieve session'], 404);
        }

        $result = [
            'id' => $session->id,
            'customer_email' => $session->customer_email ?? null,
            'amount_total' => $session->amount_total ?? null,
            'currency' => $session->currency ?? null,
            'line_items' => [],
            'subscription' => null,
            'next_billing' => null,
            'subscription_end' => null,
        ];

        // (Optional) line items retrieval omitted — not needed for compact success page

        try {
            if (!empty($session->subscription)) {
                $stripeSub = \Stripe\Subscription::retrieve($session->subscription);
                $result['subscription'] = [
                    'id' => $stripeSub->id ?? null,
                    'status' => $stripeSub->status ?? null,
                    'current_period_end' => $stripeSub->current_period_end ?? null,
                    'current_period_start' => $stripeSub->current_period_start ?? null,
                ];
                if (!empty($stripeSub->current_period_end)) {
                    $result['subscription_end'] = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)->toDateTimeString();
                }
                // Use subscription current_period_end as next billing estimate/fallback
                if (!empty($stripeSub->current_period_end)) {
                    $result['next_billing'] = \Carbon\Carbon::createFromTimestamp($stripeSub->current_period_end)->toDateTimeString();
                }
            }
        } catch (Throwable $e) {
            Log::warning('Could not retrieve subscription for session: ' . $e->getMessage(), ['session_id' => $sessionId]);
        }

        return response()->json($result);
    }
}
