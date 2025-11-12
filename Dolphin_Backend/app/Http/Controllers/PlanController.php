<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        
        $user = request()->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('organizationadmin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $plans = Plan::where('status', 'active')->get(['id', 'name', 'stripe_price_id', 'amount', 'currency', 'interval', 'description', 'slug']);
            return response()->json(['plans' => $plans]);
        } catch (\Throwable $e) {
            
            return response()->json(['plans' => []]);
        }
    }

    public function store(Request $request)
    {
        
        if (!$request->user() || !$request->user()->hasRole('superadmin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'stripe_price_id' => 'required|string|max:255',
            'interval' => 'required|in:monthly,yearly',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive'
        ]);

        $validatedData['slug'] = Str::slug($validatedData['name']);

        try {
            $plan = Plan::create($validatedData);
            return response()->json(['plan' => $plan], 201);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not create plan'], 500);
        }
    }

    public function show(string $id)
    {
        
        $user = request()->user();
        if (! $user || ! method_exists($user, 'hasRole') || ! $user->hasRole('organizationadmin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        try {
            $plan = Plan::find($id);
            if (! $plan) {
                return response()->json(['error' => 'Plan not found'], 404);
            }
            return response()->json(['plan' => $plan]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Plan not found'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        
        if (!$request->user() || !$request->user()->hasRole('superadmin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $plan = Plan::find($id);
        if (! $plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'stripe_price_id' => 'sometimes|string|max:255',
            'interval' => 'sometimes|in:monthly,yearly',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive'
        ]);

        if (isset($validatedData['name'])) {
            $validatedData['slug'] = Str::slug($validatedData['name']);
        }

        try {
            $plan->update($validatedData);
            return response()->json(['plan' => $plan]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not update plan'], 500);
        }
    }

    public function destroy(string $id)
    {
        
        $user = request()->user();
        if (!$user || !$user->hasRole('superadmin')) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        $plan = Plan::find($id);
        if (! $plan) {
            return response()->json(['error' => 'Plan not found'], 404);
        }

        try {
            $plan->delete();
            return response()->json(['message' => 'Plan deleted successfully']);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Could not delete plan'], 500);
        }
    }
}
