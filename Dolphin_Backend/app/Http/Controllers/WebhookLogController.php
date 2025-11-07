<?php

namespace App\Http\Controllers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;

class WebhookLogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = WebhookLog::query();

        // Filter by type if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by processed status if provided
        if ($request->has('processed')) {
            $query->where('processed', $request->boolean('processed'));
        }

        $webhookLogs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($webhookLogs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'event_id' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'payload' => 'required|array',
            'processed' => 'boolean'
        ]);

        $webhookLog = WebhookLog::create($validatedData);

        return response()->json(['webhook_log' => $webhookLog], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $webhookLog = WebhookLog::findOrFail($id);
        return response()->json(['webhook_log' => $webhookLog]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $webhookLog = WebhookLog::findOrFail($id);

        $validatedData = $request->validate([
            'processed' => 'sometimes|boolean'
        ]);

        $webhookLog->update($validatedData);

        return response()->json(['webhook_log' => $webhookLog]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $webhookLog = WebhookLog::findOrFail($id);
        $webhookLog->delete();

        return response()->json(['message' => 'Webhook log deleted successfully']);
    }

    /**
     * Mark webhook log as processed
     */
    public function markAsProcessed(string $id)
    {
        $webhookLog = WebhookLog::findOrFail($id);
        $webhookLog->update(['processed' => true]);

        return response()->json(['webhook_log' => $webhookLog]);
    }

    /**
     * Get unprocessed webhook logs
     */
    public function unprocessed()
    {
        $webhookLogs = WebhookLog::where('processed', false)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['webhook_logs' => $webhookLogs]);
    }
}
