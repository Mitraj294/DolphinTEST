<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the organizations.
     */
    public function index(Request $request)
    {
        $user = $request->user()->load('roles');
        $query = Organization::with([
            'user.roles',
            'user.country',
            'user.state',
            'user.city',
            'salesPerson',
            'activeSubscription',
            'address.country',
            'address.state',
            'address.city',
        ]);

        if ($user->hasRole('organizationadmin')) {
            $query->where('user_id', $user->id);
        } elseif (!$user->hasRole('superadmin')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $organizationsCollection = $query->get();

        // Prefetch latest subscription per user_id to avoid N+1 queries.
        $userIds = $organizationsCollection->pluck('user_id')->filter()->unique()->values()->all();
        $latestSubscriptions = [];
        if (!empty($userIds)) {
            $subs = Subscription::whereIn('user_id', $userIds)
                // subscriptions table uses `ends_at` (not `subscription_end`)
                ->orderByDesc('ends_at')
                ->get()
                ->groupBy('user_id')
                ->map(fn($group) => $group->first())
                ->toArray();

            // normalize to user_id => subscription model (not array)
            foreach ($subs as $uid => $s) {
                // Re-fetch model instance for each id to keep typical model behavior
                $latestSubscriptions[$uid] = Subscription::find($s['id']);
            }
        }

        $organizations = $organizationsCollection->map(fn($org) => $this->formatOrganizationPayload($org, $latestSubscriptions[$org->user_id] ?? null));

        return response()->json($organizations);
    }

    /**
     * Display the specified organization.
     */
    public function show(Request $request, Organization $organization)
    {
        $user = $request->user();
        if (!($user->hasRole('superadmin') || ($user->hasRole('organizationadmin') && $organization->user_id === $user->id))) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $organization->load([
            'user.roles',
            'user.country',
            'user.state',
            'user.city',
            'salesPerson',
            'activeSubscription',
            'address.country',
            'address.state',
            'address.city',
        ]);

        return response()->json($this->formatOrganizationPayload($organization));
    }

    /**
     * Store a newly created organization in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|string',
            'user_id' => 'required|integer|exists:users,id',
            'sales_person_id' => 'nullable|integer|exists:users,id',
            'referral_source_id' => 'nullable|integer|exists:referral_sources,id',
            'referral_other_text' => 'nullable|string',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date',
            'last_contacted' => 'nullable|datetime',
            'certified_staff' => 'nullable|integer',
        ]);

        // Map incoming fields to the organizations table columns
        $organizationData = [
            'user_id' => $validated['user_id'],
            'name' => $validated['name'],
            'size' => $validated['size'],
            'sales_person_id' => $validated['sales_person_id'] ?? null,
            'referral_source_id' => $validated['referral_source_id'] ?? null,
            'referral_other_text' => $validated['referral_other_text'] ?? null,
            'contract_start' => $validated['contract_start'] ?? null,
            'contract_end' => $validated['contract_end'] ?? null,
            'last_contacted' => $validated['last_contacted'] ?? null,
            'certified_staff' => $validated['certified_staff'] ?? 0,
        ];

        $organization = Organization::create($organizationData);

        // create organization address if provided in request
        try {
            $addr = $request->only(['address', 'address_line_1', 'address_line_2', 'country_id', 'state_id', 'city_id', 'zip', 'zip_code']);
            $hasAddr = false;
            foreach (['address', 'address_line_1', 'address_line_2', 'country_id', 'state_id', 'city_id', 'zip', 'zip_code'] as $k) {
                if (isset($addr[$k]) && $addr[$k] !== '') {
                    $hasAddr = true;
                    break;
                }
            }
            if ($hasAddr) {
                \App\Models\OrganizationAddress::create([
                    'organization_id' => $organization->id,
                    'address_line_1' => $addr['address_line_1'] ?? $addr['address'] ?? null,
                    'address_line_2' => $addr['address_line_2'] ?? null,
                    'country_id' => $addr['country_id'] ?? null,
                    'state_id' => $addr['state_id'] ?? null,
                    'city_id' => $addr['city_id'] ?? null,
                    'zip_code' => $addr['zip_code'] ?? $addr['zip'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create organization address on store', ['error' => $e->getMessage()]);
        }

        return response()->json($this->formatOrganizationPayload($organization->fresh()), 201);
    }

    /**
     * Update the specified organization in storage.
     */
    public function update(Request $request, Organization $organization)
    {
        // Using a policy for authorization is recommended here
        // $this->authorize('update', $organization);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'size' => 'sometimes|string',
            'sales_person_id' => 'nullable|integer|exists:users,id',
            'referral_source_id' => 'nullable|integer|exists:referral_sources,id',
            'referral_other_text' => 'nullable|string',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date',
            'last_contacted' => 'nullable|datetime',
            'certified_staff' => 'nullable|integer',
            'admin_email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($organization->user_id)],
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|regex:/^[6-9]\d{9}$/',
            'address_line_1' => 'sometimes|string',
            'address_line_2' => 'sometimes|string',
            'country_id' => 'sometimes|integer|exists:countries,id',
            'state_id' => 'sometimes|integer|exists:states,id',
            'city_id' => 'sometimes|integer|exists:cities,id',
            'zip_code' => 'sometimes|string|regex:/^[1-9][0-9]{5}$/',
        ]);

        try {
            DB::transaction(function () use ($organization, $validated) {
                // Build organization data from validated fields
                $organizationData = array_intersect_key($validated, array_flip([
                    'name',
                    'size',
                    'sales_person_id',
                    'referral_source_id',
                    'referral_other_text',
                    'contract_start',
                    'contract_end',
                    'last_contacted',
                    'certified_staff',
                ]));

                $userData = array_intersect_key($validated, array_flip([
                    'first_name',
                    'last_name',
                    'phone_number',
                ]));
                if (isset($validated['admin_email'])) {
                    $userData['email'] = $validated['admin_email'];
                }

                $orgAddressData = array_intersect_key($validated, array_flip([
                    'address_line_1',
                    'address_line_2',
                    'country_id',
                    'state_id',
                    'city_id',
                    'zip_code',
                ]));

                if (!empty($organizationData)) {
                    $organization->update($organizationData);
                }

                if ($organization->user && !empty($userData)) {
                    $organization->user->update($userData);
                }

                // update/create organization address from provided fields
                if (!empty($orgAddressData)) {
                    $orgAddressData['organization_id'] = $organization->id;
                    \App\Models\OrganizationAddress::updateOrCreate(
                        ['organization_id' => $organization->id],
                        $orgAddressData
                    );
                }
            });

            return response()->json($this->formatOrganizationPayload($organization->fresh()));
        } catch (\Exception $e) {
            Log::error('Failed to update organization', ['id' => $organization->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update organization.'], 500);
        }
    }

    /**
     * Remove the specified organization from storage.
     */
    public function destroy(Organization $organization)
    {
        // Using a policy for authorization is recommended here
        // $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json(null, 204);
    }


    private function formatOrganizationPayload(Organization $org, ?Subscription $providedLatestSubscription = null): array
    {
        $user = $org->user;
        
        // Use the provided latest subscription (prefetched) if available to avoid extra queries
        $latestSubscription = $providedLatestSubscription;
        if (!$latestSubscription && $org->user_id) {
            $latestSubscription = Subscription::where('user_id', $org->user_id)
                ->orderByDesc('ends_at')
                ->first();
        }

        $salesPersonName = $org->salesPerson 
            ? trim($org->salesPerson->first_name . ' ' . $org->salesPerson->last_name) 
            : null;

        // Determine primary role for the organization's user
        $userRole = $user && $user->roles->count() > 0 
            ? $user->roles->first()->name ?? null 
            : null;

        $address = $org->address;
        $referralSource = $org->referralSource;

        return [
            'id' => $org->id,
            'user_id' => $org->user_id,
            'user_role' => $userRole,
            'name' => $org->name,
            'size' => $org->size,
            'main_contact' => $user ? trim($user->first_name . ' ' . $user->last_name) : null,
            'admin_email' => $user?->email,
            'phone_number' => $user?->phone_number,
            'address_line_1' => $address?->address_line_1,
            'address_line_2' => $address?->address_line_2,
            'city' => $address?->city?->name,
            'city_id' => $address?->city_id,
            'state' => $address?->state?->name,
            'state_id' => $address?->state_id,
            'country' => $address?->country?->name,
            'country_id' => $address?->country_id,
            'zip_code' => $address?->zip_code,
            'referral_source' => $referralSource?->name,
            'referral_source_id' => $org->referral_source_id,
            'referral_other_text' => $org->referral_other_text,
            'contract_start' => $org->contract_start?->toDateString(),
            'contract_end' => $org->contract_end?->toDateString(),
            'last_contacted' => $org->last_contacted?->toDateTimeString(),
            'sales_person_id' => $org->sales_person_id,
            'sales_person' => $salesPersonName,
            'certified_staff' => $org->certified_staff,
            // Subscription status flags
            'active_subscription' => ($latestSubscription && $latestSubscription->status === 'active') ? 1 : 0,
            'expired_subscription' => ($latestSubscription && $latestSubscription->status === 'expired') ? 1 : 0,
            'no_subscription' => $latestSubscription ? 0 : 1,
        ];
    }
}
