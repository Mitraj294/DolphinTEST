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
     *
     * @param array<string,mixed> $data
     */
    public function createUserAndDetails(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Check if user is registering from a lead with existing organization
            $lead = Lead::query()->where('email', $data['email'])->first();
            /** @var \App\Models\Lead|null $lead */
            $existingOrgId = $lead ? $lead->getAttribute('organization_id') : null;

            $user = User::query()->create([
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

            /** @var \App\Models\Organization|null $org */
            $org = null;

            if ($existingOrgId) {
                // Use and UPDATE existing organization from lead
                $org = Organization::query()->find($existingOrgId);

                if ($org) {
                    // Attach user if missing
                    if (!$org->getAttribute('user_id')) {
                        $org->setAttribute('user_id', $user->getKey());
                    }

                    // Update core organization fields (allow override via registration form)
                    // Determine the appropriate referral_other_text
                    $newReferralSourceId = $data['referral_source_id'] ?? $data['find_us'] ?? $org->getAttribute('referral_source_id');
                    $newReferralOtherText = null;
                    if ((int)$newReferralSourceId === 10) {
                        $newReferralOtherText = $data['referral_other_text'] ?? $org->getAttribute('referral_other_text');
                    }

                    $org->fill([
                        'name' => $data['name'] ?? $data['organization_name'] ?? $org->getAttribute('name'),
                        'size' => $data['size'] ?? $data['organization_size'] ?? $org->getAttribute('size'),
                        'referral_source_id' => $newReferralSourceId,
                        'referral_other_text' => $newReferralOtherText,
                    ]);

                    if ($org->isDirty()) {
                        $org->save();
                    }

                    // Update or create organization address (always persist latest form values)
                    \App\Models\OrganizationAddress::query()->updateOrCreate(
                        ['organization_id' => $org->getKey()],
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
                $org = Organization::query()->create([
                    'user_id' => $user->getKey(),
                    'name' => $data['name'] ?? $data['organization_name'] ?? null,
                    'size' => $data['size'] ?? $data['organization_size'] ?? null,
                    'referral_source_id' => $data['referral_source_id'] ?? $data['find_us'] ?? null,
                    'referral_other_text' => (isset($data['referral_source_id']) && (int)$data['referral_source_id'] === 10)
                        ? ($data['referral_other_text'] ?? null)
                        : null,
                ]);

                // Create organization address
                \App\Models\OrganizationAddress::query()->create([
                    'organization_id' => $org->getKey(),
                    'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'country_id' => $data['country_id'] ?? $data['country'] ?? null,
                    'state_id' => $data['state_id'] ?? $data['state'] ?? null,
                    'city_id' => $data['city_id'] ?? $data['city'] ?? null,
                    'zip_code' => $data['zip_code'] ?? $data['zip'] ?? null,
                ]);

                // Set the organization_id on the user
                if ($org) {
                    $user->setAttribute('organization_id', $org->getKey());
                    $user->save();
                }
            }

            $role = Role::query()->where('name', 'user')->first();
            if ($role) {
                $user->roles()->attach($role);
            }

            return $user;
        });
    }

    public function updateLeadStatus(string $email): void
    {
        try {
            $lead = Lead::query()->where('email', $email)->first();
            if ($lead) {
                $lead->update(['status' => 'Registered', 'registered_at' => now()]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update lead status after registration', ['email' => $email, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Build payload for a user to return to the frontend.
     *
     * @param User $user
     * @return array<string,mixed>
     */
    public function buildUserPayload(User $user): array
    {
        // Ensure we have country and roles loaded and fetch the owning organization
        $user->loadMissing(['country', 'roles']);
    /** @var \App\Models\Organization|null $org */
        $org = Organization::query()->with('address')->where('user_id', $user->getKey())->first();

        $orgPayload = null;
        if ($org) {
            $lastContacted = $org->getAttribute('last_contacted');
            $lastContactedStr = null;
            if ($lastContacted instanceof \DateTimeInterface) {
                $lastContactedStr = $lastContacted->format('Y-m-d H:i:s');
            } elseif ($lastContacted) {
                $lastContactedStr = (string) $lastContacted;
            }

            $orgPayload = [
                'id' => $org->getKey(),
                'name' => $org->getAttribute('name'),
                'size' => $org->getAttribute('size'),
                'referral_source_id' => $org->getAttribute('referral_source_id'),
                'referral_other_text' => $org->getAttribute('referral_other_text'),
                'last_contacted' => $lastContactedStr,
                'address' => null,
            ];

            $addr = $org->getRelationValue('address');
            if ($addr) {
                $orgPayload['address'] = [
                    'address_line_1' => $addr->getAttribute('address_line_1'),
                    'address_line_2' => $addr->getAttribute('address_line_2'),
                    'zip_code' => $addr->getAttribute('zip_code'),
                    'country_id' => $addr->getAttribute('country_id'),
                    'state_id' => $addr->getAttribute('state_id'),
                    'city_id' => $addr->getAttribute('city_id'),
                ];
            }
        }

        return [
            'id' => $user->getKey(),
            'email' => $user->getAttribute('email'),
            'role' => (
                ($user->getRelationValue('roles') instanceof \Illuminate\Support\Collection) ?
                    (($user->getRelationValue('roles')->first())?->getAttribute('name') ?? 'user') : 'user'
            ),
            'first_name' => $user->getAttribute('first_name'),
            'last_name' => $user->getAttribute('last_name'),
            'phone_number' => $user->getAttribute('phone_number') ?? null,
            'country' => ($user->getRelationValue('country')?->getAttribute('name')) ?? null,
            'country_id' => $user->getAttribute('country_id') ?? null,
            'organization_id' => $org ? $org->getKey() : null,
            'organization_name' => $org ? $org->getAttribute('name') : null, // keep for frontend compatibility
            'name' => $org ? $org->getAttribute('name') : null, // new field name
            // Helpful organization metadata consumed by the frontend
            'organization' => $orgPayload,
        ];
    }

    /**
     * @param User $user
     * @param array<string,mixed> $userData
     * @param array<string,mixed> $detailsData
     */
    public function updateUserRecord(User $user, array $userData, array $detailsData): void
    {
        $user->fill([
            'email' => $userData['email'] ?? $detailsData['email'] ?? $user->getAttribute('email'),
            'first_name' => $detailsData['first_name'] ?? $user->getAttribute('first_name'),
            'last_name' => $detailsData['last_name'] ?? $user->getAttribute('last_name'),
        ]);

        if ($user->isDirty()) {
            $user->save();
        }
    }

    /**
     * @param User $user
     * @param array<string,mixed> $detailsData
     */
    public function updateUserDetailsRecord(User $user, array $detailsData): void
    {
        if (empty($detailsData)) {
            return;
        }

    // Store phone into the users.phone_number column. Accept either 'phone' or 'phone_number'
        $user->setAttribute('phone_number', $detailsData['phone_number'] ?? $detailsData['phone'] ?? $user->getAttribute('phone_number'));

        if (isset($detailsData['country'])) {
            $user->setAttribute('country_id', $this->resolveCountryId($detailsData['country']));
        }

        if (isset($detailsData['referral_source_id'])) {
            $user->setAttribute('referral_source_id', $detailsData['referral_source_id']);
        } elseif (isset($detailsData['find_us'])) {
            $user->setAttribute('referral_source_id', $detailsData['find_us']);
        }

        if ($user->isDirty()) {
            $user->save();
        }
    }

    /**
     * @param mixed $countryInput
     */
    private function resolveCountryId($countryInput): ?int
    {
        if (is_numeric($countryInput)) {
            return (int) $countryInput;
        }

        if (is_string($countryInput) && !empty(trim($countryInput))) {
            $country = Country::query()->where('name', trim($countryInput))->orWhere('code', trim($countryInput))->first();
            return $country ? $country->getKey() : null;
        }

        return null;
    }
}
