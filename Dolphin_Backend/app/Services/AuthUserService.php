<?php

namespace App\Services;

use App\Models\User;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Country;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthUserService
{
    /**
     * Create a user and their associated details in a transaction.
     */
    public function createUserAndDetails(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Check if user is registering from a lead with existing organization
            $lead = Lead::where('email', $data['email'])->first();
            $existingOrgId = $lead?->organization_id ?? null;

            $user = User::create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'] ?? $data['phone'] ?? null,
                'referral_source_id' => $data['referral_source_id'] ?? $data['find_us'] ?? null,
                'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                'address_line_2' => $data['address_line_2'] ?? null,
                'country_id' => $data['country_id'] ?? $data['country'] ?? null,
                'state_id' => $data['state_id'] ?? $data['state'] ?? null,
                'city_id' => $data['city_id'] ?? $data['city'] ?? null,
                'zip_code' => $data['zip_code'] ?? $data['zip'] ?? null,
                'organization_id' => $existingOrgId, // Link to existing org if available
            ]);

            $org = null;

            if ($existingOrgId) {
                // Use and UPDATE existing organization from lead
                $org = Organization::find($existingOrgId);

                if ($org) {
                    // Attach user if missing
                    if (!$org->user_id) {
                        $org->user_id = $user->id;
                    }

                    // Update core organization fields (allow override via registration form)
                    // Determine the appropriate referral_other_text
                    $newReferralSourceId = $data['referral_source_id'] ?? $data['find_us'] ?? $org->referral_source_id;
                    $newReferralOtherText = null;
                    if ((int)$newReferralSourceId === 10) {
                        $newReferralOtherText = $data['referral_other_text'] ?? $org->referral_other_text;
                    }

                    $org->fill([
                        'name' => $data['name'] ?? $data['organization_name'] ?? $org->name,
                        'size' => $data['size'] ?? $data['organization_size'] ?? $org->size,
                        'referral_source_id' => $newReferralSourceId,
                        'referral_other_text' => $newReferralOtherText,
                    ]);

                    if ($org->isDirty()) {
                        $org->save();
                    }

                    // Update or create organization address (always persist latest form values)
                    \App\Models\OrganizationAddress::updateOrCreate(
                        ['organization_id' => $org->id],
                        [
                            'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                            'address_line_2' => $data['address_line_2'] ?? null,
                            'country_id' => $data['country_id'] ?? $data['country'] ?? null,
                            'state_id' => $data['state_id'] ?? $data['state'] ?? null,
                            'city_id' => $data['city_id'] ?? $data['city'] ?? null,
                            'zip_code' => $data['zip_code'] ?? $data['zip'] ?? null,
                        ]
                    );
                }
            } else {
                // Create new organization
                $org = Organization::create([
                    'user_id' => $user->id,
                    'name' => $data['name'] ?? $data['organization_name'] ?? null,
                    'size' => $data['size'] ?? $data['organization_size'] ?? null,
                    'referral_source_id' => $data['referral_source_id'] ?? $data['find_us'] ?? null,
                    'referral_other_text' => (isset($data['referral_source_id']) && (int)$data['referral_source_id'] === 10)
                        ? ($data['referral_other_text'] ?? null)
                        : null,
                ]);

                // Create organization address
                \App\Models\OrganizationAddress::create([
                    'organization_id' => $org->id,
                    'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'country_id' => $data['country_id'] ?? $data['country'] ?? null,
                    'state_id' => $data['state_id'] ?? $data['state'] ?? null,
                    'city_id' => $data['city_id'] ?? $data['city'] ?? null,
                    'zip_code' => $data['zip_code'] ?? $data['zip'] ?? null,
                ]);

                // Set the organization_id on the user
                if ($org) {
                    $user->organization_id = $org->id;
                    $user->save();
                }
            }

            $user->roles()->attach(Role::where('name', 'user')->first());

            return $user;
        });
    }

    public function updateLeadStatus(string $email): void
    {
        try {
            $lead = Lead::where('email', $email)->first();
            if ($lead) {
                $lead->update(['status' => 'Registered', 'registered_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update lead status after registration', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    public function buildUserPayload(User $user): array
    {
        // Ensure we have country and roles loaded and fetch the owning organization
        $user->loadMissing(['country', 'roles']);
        $org = Organization::with('address')->where('user_id', $user->id)->first();

        $orgPayload = null;
        if ($org) {
            $orgPayload = [
                'id' => $org->id,
                'name' => $org->name,
                'size' => $org->size,
                'referral_source_id' => $org->referral_source_id,
                'referral_other_text' => $org->referral_other_text,
                'last_contacted' => $org->last_contacted?->toDateTimeString(),
                'address' => null,
            ];

            if ($org->address) {
                $orgPayload['address'] = [
                    'address_line_1' => $org->address->address_line_1,
                    'address_line_2' => $org->address->address_line_2,
                    'zip_code' => $org->address->zip_code,
                    'country_id' => $org->address->country_id,
                    'state_id' => $org->address->state_id,
                    'city_id' => $org->address->city_id,
                ];
            }
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->roles->first()->name ?? 'user',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone_number' => $user->phone_number ?? null,
            'country' => $user->country->name ?? null,
            'country_id' => $user->country_id ?? null,
            'organization_id' => $org?->id,
            'organization_name' => $org?->name, // keep for frontend compatibility
            'name' => $org?->name, // new field name
            // Helpful organization metadata consumed by the frontend
            'organization' => $orgPayload,
        ];
    }

    public function updateUserRecord(User $user, array $userData, array $detailsData): void
    {
        $user->fill([
            'email' => $userData['email'] ?? $detailsData['email'] ?? $user->email,
            'first_name' => $detailsData['first_name'] ?? $user->first_name,
            'last_name' => $detailsData['last_name'] ?? $user->last_name,
        ]);

        if ($user->isDirty()) {
            $user->save();
        }
    }

    public function updateUserDetailsRecord(User $user, array $detailsData): void
    {
        if (empty($detailsData)) {
            return;
        }

    // Store phone into the users.phone_number column. Accept either 'phone' or 'phone_number'
    $user->phone_number = $detailsData['phone_number'] ?? $detailsData['phone'] ?? $user->phone_number;

        if (isset($detailsData['country'])) {
            $user->country_id = $this->resolveCountryId($detailsData['country']);
        }

        if (isset($detailsData['referral_source_id'])) {
            $user->referral_source_id = $detailsData['referral_source_id'];
        } elseif (isset($detailsData['find_us'])) {
            $user->referral_source_id = $detailsData['find_us'];
        }

        if ($user->isDirty()) {
            $user->save();
        }
    }

    private function resolveCountryId($countryInput): ?int
    {
        if (is_numeric($countryInput)) {
            return (int) $countryInput;
        }

        if (is_string($countryInput) && !empty(trim($countryInput))) {
            $country = Country::where('name', trim($countryInput))->orWhere('code', trim($countryInput))->first();
            return $country?->id;
        }

        return null;
    }
}
