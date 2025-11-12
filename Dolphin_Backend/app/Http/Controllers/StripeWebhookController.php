<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use App\Models\WebhookLog;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentMethod;
use Stripe\Stripe;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    
    public function handleWebhook(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');
        if (! $secret) {
            Log::error('Stripe webhook secret is missing. Rejecting webhook.');

            return response()->json(['error' => 'Webhook not configured'], 503);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (UnexpectedValueException $exception) {
            $this->storeRawWebhookLog('invalid_payload', $payload, $exception->getMessage());
            Log::error('Stripe webhook payload could not be parsed', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException $exception) {
            $this->storeRawWebhookLog('invalid_signature', $payload, $exception->getMessage());
            Log::error('Stripe webhook signature verification failed', ['error' => $exception->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        Log::info('Stripe webhook received', ['event_type' => $event->type, 'event_id' => $event->id]);

        try {
            $stripeSecret = config('services.stripe.secret');
            if (! $stripeSecret) {
                throw new \RuntimeException('Stripe secret is missing.');
            }

            Stripe::setApiKey($stripeSecret);

            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                'invoice.paid', 'invoice.payment_succeeded' => $this->handleInvoicePaid($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                default => Log::info('Stripe webhook type not handled explicitly', ['type' => $event->type]),
            };

            $this->storeWebhookLog($event, true, null);
        } catch (\Throwable $exception) {
            $this->storeWebhookLog($event, false, $exception->getMessage());
            Log::error('Stripe webhook processing failed', [
                'event_type' => $event->type,
                'event_id' => $event->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }

        return response()->json(['status' => 'processed']);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $userId = $session->client_reference_id ?? null;
        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            throw new \RuntimeException('Checkout session missing valid user reference.');
        }

        $metadata = $this->convertStripeObjectToArray($session->metadata ?? []);
        $planId = $metadata['plan_id'] ?? null;
        $plan = $planId ? Plan::find($planId) : null;
        $stripeSubscriptionId = $session->subscription ?? null;
        $stripeSubscription = null;

        
        
        
        if (! $plan) {
            $priceId = null;

            
            if ($stripeSubscriptionId) {
                try {
                    
                    $stripeSubscription = StripeSubscription::retrieve($stripeSubscriptionId, [
                        'expand' => ['items.data.price', 'default_payment_method'],
                    ]);

                    $priceId = $stripeSubscription->items->data[0]->price->id ?? null;
                } catch (\Throwable $e) {
                    Log::warning('Failed to retrieve Stripe subscription while inferring plan', [
                        'subscription_id' => $stripeSubscriptionId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            
            if (empty($priceId) && ! empty($session->id)) {
                try {
                    $checkout = \Stripe\Checkout\Session::retrieve($session->id, [
                        'expand' => ['line_items.data.price'],
                    ]);
                    $priceId = $checkout->line_items->data[0]->price->id ?? null;
                } catch (\Throwable $e) {
                    Log::warning('Failed to retrieve Checkout Session while inferring plan', [
                        'session_id' => $session->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (! empty($priceId)) {
                $plan = Plan::where('stripe_price_id', $priceId)->first();
            }
        }

        if (! $plan) {
            throw new \RuntimeException('Checkout session missing valid plan reference.');
        }

        if ($session->customer && $user->stripe_id !== $session->customer) {
            $user->forceFill(['stripe_id' => $session->customer])->save();
        }

        
        if (empty($stripeSubscription)) {
            $stripeSubscriptionId = $session->subscription ?? null;
            if (! $stripeSubscriptionId) {
                throw new \RuntimeException('Checkout session lacks Stripe subscription id.');
            }

            $stripeSubscription = StripeSubscription::retrieve($stripeSubscriptionId, [
                'expand' => ['default_payment_method'],
            ]);
        }

        $paymentMethod = null;
        if ($stripeSubscription->default_payment_method) {
            try {
                $paymentMethod = PaymentMethod::retrieve($stripeSubscription->default_payment_method);
            } catch (\Throwable $exception) {
                Log::warning('Unable to retrieve Stripe payment method for subscription', [
                    'subscription_id' => $stripeSubscriptionId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $startedAt = $this->timestampToCarbon($stripeSubscription->current_period_start ?? $session->created ?? null);
        $currentPeriodEnd = $this->timestampToCarbon($stripeSubscription->current_period_end ?? null);

        
        $endsAt = $currentPeriodEnd;
        if (! $endsAt && $plan) {
            if ($plan->type === 'monthly') {
                $endsAt = $startedAt ? $startedAt->copy()->addMonth() : null;
            } elseif ($plan->type === 'yearly') {
                $endsAt = $startedAt ? $startedAt->copy()->addYear() : null;
            }
        }

        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $stripeSubscriptionId],
            [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'stripe_customer_id' => $session->customer ?? $stripeSubscription->customer ?? null,
                'status' => $stripeSubscription->status ?? ($session->payment_status === 'paid' ? 'active' : $session->payment_status),
                'started_at' => $startedAt,
                'current_period_end' => $currentPeriodEnd,
                'trial_ends_at' => $this->timestampToCarbon($stripeSubscription->trial_end ?? null),
                'ends_at' => $endsAt,
                'cancel_at_period_end' => (bool) ($stripeSubscription->cancel_at_period_end ?? false),
                'is_paused' => ! empty($stripeSubscription->pause_collection),
                'default_payment_method_id' => $stripeSubscription->default_payment_method ?? null,
                'payment_method_type' => $paymentMethod->type ?? null,
                'payment_method_brand' => $paymentMethod->card->brand ?? null,
                'payment_method_last4' => $paymentMethod->card->last4 ?? null,
                'payment_method_label' => $this->formatPaymentMethodLabel($paymentMethod),
            ]
        );

        
        try {
            if (! empty($user) && ! empty($user->email)) {
                $lead = Lead::where('email', $user->email)->first();
                if ($lead) {
                    $lead->status = 'Registered';
                    if (empty($lead->registered_at)) {
                        $lead->registered_at = now();
                    }
                    $lead->save();
                    Log::info('Stripe webhook: marked lead Registered after checkout', ['lead_id' => $lead->id, 'user_id' => $user->id]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Stripe webhook: failed to update lead status after checkout: ' . $e->getMessage());
        }
    }

    private function handleInvoicePaid(object $invoice): void
    {
        
        
        
        $stripeSubscriptionId = $invoice->subscription ?? null;

        if ($stripeSubscriptionId) {
            $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
        } else {
            $subscription = null;
        }

        if (! $subscription) {
            
            $customerId = $invoice->customer ?? null;
            if ($customerId) {
                $subscription = Subscription::where('stripe_customer_id', $customerId)
                    ->orderByDesc('current_period_end')
                    ->first();
            }
        }

        if (! $subscription) {
            
            Log::warning('Invoice webhook arrived without resolvable subscription', [
                'invoice_id' => $invoice->id ?? null,
                'customer' => $invoice->customer ?? null,
            ]);

            return;
        }

        if (SubscriptionInvoice::where('stripe_invoice_id', $invoice->id)->exists()) {
            Log::info('Invoice webhook already processed', ['stripe_invoice_id' => $invoice->id]);

            return;
        }

        
        $dueTs = $invoice->due_date ?? $invoice->created ?? null;
        $dueDate = $this->timestampToCarbon($dueTs) ?? $this->timestampToCarbon($invoice->created ?? null)?->addDay();

        $statusTransitions = $invoice->status_transitions ?? null;
        $paidAtValue = $statusTransitions ? ($statusTransitions->paid_at ?? null) : null;
        $paidAt = $this->timestampToCarbon($paidAtValue);

        SubscriptionInvoice::create([
            'subscription_id' => $subscription->id,
            'stripe_invoice_id' => $invoice->id,
            'amount_due' => $this->formatAmount($invoice->amount_due ?? null),
            'amount_paid' => $this->formatAmount($invoice->amount_paid ?? null),
            'currency' => $invoice->currency ?? config('services.stripe.currency', 'usd'),
            'status' => $invoice->status ?? 'paid',
            'due_date' => $dueDate,
            'paid_at' => $paidAt,
            'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
        ]);
    }

    private function storeWebhookLog(Event $event, bool $processed, ?string $error): void
    {
        WebhookLog::create([
            'event_id' => $event->id,
            'type' => $event->type,
            'payload' => $this->convertStripeObjectToArray($event->data->object ?? []),
            'processed' => $processed,
            'error' => $error,
        ]);
    }

    private function storeRawWebhookLog(string $type, string $payload, ?string $error): void
    {
        WebhookLog::create([
            'event_id' => null,
            'type' => $type,
            'payload' => ['raw' => $payload],
            'processed' => false,
            'error' => $error,
        ]);
    }

    private function convertStripeObjectToArray($object): array
    {
        
        
        $initialDepth = 3;

        $convert = function ($value, $depth) use (&$convert) {
            if ($depth < 0) {
                return null;
            }

            if (is_array($value)) {
                $out = [];
                foreach ($value as $k => $v) {
                    $out[$k] = $convert($v, $depth - 1);
                }

                return $out;
            }

            if (is_object($value)) {
                
                if (method_exists($value, 'toArray')) {
                    $arr = $value->toArray();
                } elseif ($value instanceof \JsonSerializable) {
                    $arr = $value->jsonSerialize();
                } else {
                    
                    try {
                        $arr = json_decode(json_encode($value), true) ?: [];
                    } catch (\Throwable $e) {
                        return null;
                    }
                }

                
                $out = [];
                foreach ($arr as $k => $v) {
                    $out[$k] = $convert($v, $depth - 1);
                }

                return $out;
            }

            
            return $value;
        };

        return $convert($object, $initialDepth) ?: [];
    }

    
    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $stripeSubscription = $this->convertStripeObjectToArray($stripeSubscription);
        $stripeId = $stripeSubscription['id'] ?? null;
        if (! $stripeId) {
            Log::warning('Received subscription.updated webhook without id');
            return;
        }

        $sub = Subscription::where('stripe_subscription_id', $stripeId)->first();
        if (! $sub) {
            Log::info('Subscription update for unknown subscription; skipping', ['stripe_subscription_id' => $stripeId]);
            return;
        }

        $currentPeriodEnd = isset($stripeSubscription['current_period_end']) ? $this->timestampToCarbon($stripeSubscription['current_period_end']) : null;
        $trialEndsAt = isset($stripeSubscription['trial_end']) ? $this->timestampToCarbon($stripeSubscription['trial_end']) : null;
        $cancelAtPeriodEnd = ! empty($stripeSubscription['cancel_at_period_end']);
        $isPaused = ! empty($stripeSubscription['pause_collection']);

        $sub->forceFill([
            'status' => $stripeSubscription['status'] ?? $sub->status,
            'current_period_end' => $currentPeriodEnd,
            'trial_ends_at' => $trialEndsAt,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'is_paused' => $isPaused,
        ])->save();
    }

    
    private function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $stripeSubscription = $this->convertStripeObjectToArray($stripeSubscription);
        $stripeId = $stripeSubscription['id'] ?? null;
        if (! $stripeId) {
            Log::warning('Received subscription.deleted webhook without id');
            return;
        }

        $sub = Subscription::where('stripe_subscription_id', $stripeId)->first();
        if (! $sub) {
            Log::info('Subscription deleted for unknown subscription; skipping', ['stripe_subscription_id' => $stripeId]);
            return;
        }

        
        $currentPeriodEnd = isset($stripeSubscription['current_period_end']) ? $this->timestampToCarbon($stripeSubscription['current_period_end']) : null;

        if ($currentPeriodEnd) {
            $sub->forceFill(['status' => $stripeSubscription['status'] ?? 'canceled', 'current_period_end' => $currentPeriodEnd, 'ends_at' => $currentPeriodEnd])->save();
        } else {
            $sub->delete(); 
        }
    }

    private function timestampToCarbon($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            Log::warning('Unable to parse timestamp', ['value' => $value, 'error' => $exception->getMessage()]);
        }

        return null;
    }

    private function formatAmount($amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        return number_format(((int) $amount) / 100, 2, '.', '');
    }

    private function formatPaymentMethodLabel(?PaymentMethod $paymentMethod): ?string
    {
        if (! $paymentMethod) {
            return null;
        }

        if ($paymentMethod->type === 'card' && $paymentMethod->card) {
            return sprintf('%s ****%s', ucfirst($paymentMethod->card->brand), $paymentMethod->card->last4);
        }

        return ucfirst($paymentMethod->type);
    }
}
