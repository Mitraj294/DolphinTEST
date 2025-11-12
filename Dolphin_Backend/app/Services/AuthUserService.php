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
    
    public function createUserAndDetails(array $data): User
    {
        return DB::transaction(function () use ($data) {
            
            $lead = Lead::query()->where('email', $data['email'])->first();
            
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
                'organization_id' => $existingOrgId, 
            ]);

            
            $org = null;

            if ($existingOrgId) {
                
                $org = Organization::query()->find($existingOrgId);

                if ($org) {
                    
                    if (!$org->getAttribute('user_id')) {
                        $org->setAttribute('user_id', $user->getKey());
                    }

                    
                    
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
                
                $org = Organization::query()->create([
                    'user_id' => $user->getKey(),
                    'name' => $data['name'] ?? $data['organization_name'] ?? null,
                    'size' => $data['size'] ?? $data['organization_size'] ?? null,
                    'referral_source_id' => $data['referral_source_id'] ?? $data['find_us'] ?? null,
                    'referral_other_text' => (isset($data['referral_source_id']) && (int)$data['referral_source_id'] === 10)
                        ? ($data['referral_other_text'] ?? null)
                        : null,
                ]);

                
                \App\Models\OrganizationAddress::query()->create([
                    'organization_id' => $org->getKey(),
                    'address_line_1' => $data['address_line_1'] ?? $data['address'] ?? null,
                    'address_line_2' => $data['address_line_2'] ?? null,
                    'country_id' => $data['country_id'] ?? $data['country'] ?? null,
                    'state_id' => $data['state_id'] ?? $data['state'] ?? null,
                    'city_id' => $data['city_id'] ?? $data['city'] ?? null,
                    'zip_code' => $data['zip_code'] ?? $data['zip'] ?? null,
                ]);

                
                if ($org) {
                    $user->setAttribute('organization_id', $org->getKey());
                    $user->save();
                }
            }

            
            
            $preferredRole = 'user';
            if (!empty($data['lead_id']) || ($lead && $lead->getAttribute('organization_id'))) {
                $preferredRole = 'organizationadmin';
            }

            $role = Role::query()->where('name', $preferredRole)->first();
            if ($role) {
                $user->roles()->attach($role);
            } else {
                
                $fallback = Role::query()->where('name', 'user')->first();
                if ($fallback) {
                    $user->roles()->attach($fallback);
                }
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

    
    public function buildUserPayload(User $user): array
    {
        
        $user->loadMissing(['country', 'roles']);
    
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
            'organization_name' => $org ? $org->getAttribute('name') : null, 
            'name' => $org ? $org->getAttribute('name') : null, 
            
            'organization' => $orgPayload,
        ];
    }

    
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

    
    public function updateUserDetailsRecord(User $user, array $detailsData): void
    {
        if (empty($detailsData)) {
            return;
        }

    
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
