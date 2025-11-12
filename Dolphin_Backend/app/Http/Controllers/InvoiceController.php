<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionInvoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $query = SubscriptionInvoice::query();

        if ($request->has('subscription_id')) {
            $query->where('subscription_id', $request->input('subscription_id'));
        }

        $perPage = (int) $request->input('per_page', 50);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'stripe_invoice_id' => 'required|string|max:255',
            'amount_due' => 'required|numeric',
            'amount_paid' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'status' => 'required|in:paid,open,uncollectible',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'hosted_invoice_url' => 'nullable|url',
        ]);

        $invoice = SubscriptionInvoice::create([
            'subscription_id' => $validated['subscription_id'],
            'stripe_invoice_id' => $validated['stripe_invoice_id'],
            
            'amount_due' => (string) $validated['amount_due'],
            'amount_paid' => (string) $validated['amount_paid'],
            'currency' => strtoupper($validated['currency']),
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?? null,
            'paid_at' => $validated['paid_at'] ?? null,
            'hosted_invoice_url' => $validated['hosted_invoice_url'] ?? null,
        ]);

        return response()->json(['invoice' => $invoice], 201);
    }

    
    public function show(string $id)
    {
        $user = request()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $invoice = SubscriptionInvoice::findOrFail($id);
        return response()->json(['invoice' => $invoice]);
    }

    
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $invoice = SubscriptionInvoice::findOrFail($id);

        $validated = $request->validate([
            'amount_due' => 'sometimes|numeric',
            'amount_paid' => 'sometimes|numeric',
            'currency' => 'sometimes|string|max:10',
            'status' => 'sometimes|in:paid,open,uncollectible',
            'due_date' => 'nullable|date',
            'paid_at' => 'nullable|date',
            'hosted_invoice_url' => 'nullable|url',
        ]);

        if (array_key_exists('amount_due', $validated)) {
            $validated['amount_due'] = (string) $validated['amount_due'];
        }
        if (array_key_exists('amount_paid', $validated)) {
            $validated['amount_paid'] = (string) $validated['amount_paid'];
        }
        if (array_key_exists('currency', $validated)) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $invoice->update($validated);

        return response()->json(['invoice' => $invoice]);
    }

    
    public function destroy(string $id)
    {
        $user = request()->user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $invoice = SubscriptionInvoice::findOrFail($id);
        $invoice->delete();

        return response()->json(['message' => 'Invoice deleted successfully']);
    }
}
