<?php

// Usage: php tools/backfill_invoices_from_webhook_logs.php [--dry-run]
// Parses webhook_logs rows (type = invoice.paid) and upserts rows into subscription_invoices.

$dryRun = in_array('--dry-run', $argv, true);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;

function valueOr($arr, $path, $default = null) {
    $keys = is_array($path) ? $path : explode('.', $path);
    $ref = $arr;
    foreach ($keys as $k) {
        if (is_array($ref) && array_key_exists($k, $ref)) {
            $ref = $ref[$k];
        } else {
            return $default;
        }
    }
    return $ref;
}

$rows = DB::table('webhook_logs')
    ->whereIn('type', ['invoice.paid', 'invoice.payment_succeeded'])
    ->orderBy('id', 'asc')
    ->get();

if ($rows->isEmpty()) {
    echo "No webhook_logs of type invoice.paid/payment_succeeded found.\n";
    exit(0);
}

$inserted = 0;
$updated = 0;
$skipped = 0;

foreach ($rows as $row) {
    $payload = $row->payload;
    if (is_string($payload)) {
        $decoded = json_decode($payload, true);
    } elseif (is_array($payload)) {
        $decoded = $payload;
    } else {
        // Some drivers may cast JSON to object
        $decoded = json_decode(json_encode($payload), true);
    }

    if (!is_array($decoded) || empty($decoded)) {
        $skipped++;
        continue;
    }

    // Some earlier logs may have stored Stripe SDK internals; extract the original values bag
    foreach (["\0*\0_originalValues", "\u0000*\u0000_originalValues", "_originalValues"] as $internalKey) {
        if (array_key_exists($internalKey, $decoded) && is_array($decoded[$internalKey])) {
            $decoded = $decoded[$internalKey];
            break;
        }
    }

    $stripeInvoiceId = $decoded['id'] ?? null;
    if (!$stripeInvoiceId) {
        $skipped++;
        continue;
    }

    $stripeSubscriptionId = $decoded['subscription']
        ?? valueOr($decoded, 'lines.data.0.subscription')
        ?? null;
    if (!$stripeSubscriptionId) {
        $skipped++;
        continue;
    }

    $subscription = Subscription::where('stripe_subscription_id', $stripeSubscriptionId)->first();
    if (!$subscription) {
        $skipped++;
        continue;
    }

    $amountPaidCents = $decoded['amount_paid'] ?? null;
    $amountDueCents = $decoded['amount_due'] ?? null;

    $data = [
        'subscription_id'   => $subscription->id,
        'amount_due'        => is_numeric($amountDueCents) ? ($amountDueCents / 100) : null,
        'amount_paid'       => is_numeric($amountPaidCents) ? ($amountPaidCents / 100) : null,
        'currency'          => $decoded['currency'] ?? config('services.stripe.currency', 'usd'),
        'status'            => $decoded['status'] ?? 'paid',
        'due_date'          => null,
        'paid_at'           => valueOr($decoded, 'status_transitions.paid_at')
                                ? date('Y-m-d H:i:s', (int) valueOr($decoded, 'status_transitions.paid_at'))
                                : now(),
        'hosted_invoice_url'=> $decoded['hosted_invoice_url'] ?? null,
    ];

    $exists = SubscriptionInvoice::where('stripe_invoice_id', $stripeInvoiceId)->first();
    if ($dryRun) {
        echo ($exists ? 'Would update ' : 'Would insert ') . $stripeInvoiceId . " for subscription #{$subscription->id}\n";
        continue;
    }

    if ($exists) {
        $exists->fill($data)->save();
        $updated++;
    } else {
        $data['stripe_invoice_id'] = $stripeInvoiceId;
        SubscriptionInvoice::create($data);
        $inserted++;
    }
}

echo "Done. Inserted: {$inserted}, Updated: {$updated}, Skipped: {$skipped}\n";
