<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use App\Mail\LeadAssessmentRegistrationMail;

// LeadController
// Controller for managing Lead operations:
//  - Create, update, show, list, delete leads
//  - Generate registration and agreement email templates
//  - Prefill registration form data
//  - Get distinct 'find_us' options for leads
// Structure:
// 1. Validation Rules (LeadValidationRules)
// 2. Message Constants (Message)
// 3. Controller Methods



// 3. LeadController

class LeadController extends Controller
{
    // Validation constants (kept local to the controller to comply with PSR-12
    // single-class-per-file expectations).
    private const REQUIRED_STRING = 'required|string';
    private const REQUIRED_EMAIL = 'required|email';
    private const OPTIONAL_STRING = 'nullable|string';

    // Common messages
    private const MESSAGE = 'Lead Not Found';
    // Update an existing lead.
    // Handles PATCH for notes-only update.
    // @param Request $request
    // @param int $id
    // @return \Illuminate\Http\JsonResponse

    public function update(Request $request, int $id): JsonResponse
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json(['message' => self::MESSAGE], 404);
        }

        // Full update validation rules
        $data = $request->validate([
            'first_name'         => self::REQUIRED_STRING,
            'last_name'          => self::REQUIRED_STRING,
            'email'              => self::REQUIRED_EMAIL,
            'phone_number'       => 'required|regex:/^[6-9]\d{9}$/',
            'status'             => self::OPTIONAL_STRING,
            'organization_id'    => 'nullable|integer|exists:organizations,id',
            'organization_name'  => self::OPTIONAL_STRING,
            'organization_size'  => self::OPTIONAL_STRING,
            'referral_source_id' => 'nullable|integer|exists:referral_sources,id',
            'referral_other_text' => self::OPTIONAL_STRING,
            'find_us'            => self::OPTIONAL_STRING,
            'address_line_1'     => self::OPTIONAL_STRING,
            'address_line_2'     => self::OPTIONAL_STRING,
            'zip_code'           => self::OPTIONAL_STRING,
            'country_id'         => 'nullable|integer|exists:countries,id',
            'state_id'           => 'nullable|integer|exists:states,id',
            'city_id'            => 'nullable|integer|exists:cities,id',
        ]);

        // Update the lead itself for its own columns only
        $leadUpdate = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'status' => $data['status'] ?? $lead->status,
        ];
        $lead->update($leadUpdate);

        // Handle related Organization + Address updates when provided
        $orgFieldKeys = [
            'organization_name', 'organization_size', 'referral_source_id', 'referral_other_text',
            'address_line_1', 'address_line_2', 'country_id', 'state_id', 'city_id', 'zip_code'
        ];
        $hasOrgRelatedInput = collect($orgFieldKeys)->some(fn($k) => array_key_exists($k, $data) && $data[$k] !== null && $data[$k] !== '');

        if ($hasOrgRelatedInput || !empty($data['organization_id'])) {
            // Resolve existing or create a new organization if fields provided
            $org = null;
            if (!empty($lead->organization_id)) {
                $org = \App\Models\Organization::find($lead->organization_id);
            } elseif (!empty($data['organization_id'])) {
                $org = \App\Models\Organization::find($data['organization_id']);
            }

            if (!$org) {
                // Create minimal organization if any org fields present
                $org = \App\Models\Organization::create([
                    'name' => $data['organization_name'] ?? null,
                    'size' => $data['organization_size'] ?? null,
                    'referral_source_id' => $data['referral_source_id'] ?? null,
                    'referral_other_text' => (isset($data['referral_source_id']) && (int)$data['referral_source_id'] === 10)
                        ? ($data['referral_other_text'] ?? null)
                        : null,
                ]);
                $lead->organization_id = $org->id;
                $lead->save();
            } else {
                // Update organization core fields if provided
                $newReferralSourceId = $data['referral_source_id'] ?? $org->referral_source_id;
                $newReferralOtherText = null;
                if ((int)$newReferralSourceId === 10) {
                    $newReferralOtherText = $data['referral_other_text'] ?? $org->referral_other_text;
                }

                $org->fill([
                    'name' => $data['organization_name'] ?? $org->name,
                    'size' => $data['organization_size'] ?? $org->size,
                    'referral_source_id' => $newReferralSourceId,
                    'referral_other_text' => $newReferralOtherText,
                ]);
                if ($org->isDirty()) {
                    $org->save();
                }
            }

            // Update or create address for organization when address fields provided
            $addressPayload = array_intersect_key($data, array_flip([
                'address_line_1', 'address_line_2', 'country_id', 'state_id', 'city_id', 'zip_code'
            ]));
            if (!empty($addressPayload)) {
                $addressPayload['organization_id'] = $org->id;
                \App\Models\OrganizationAddress::updateOrCreate(
                    ['organization_id' => $org->id],
                    $addressPayload
                );
            }
        }

        // Return the updated lead with minimal org info for UI coherence
        $lead->load('organization.address', 'organization.referralSource');
        return response()->json(['message' => 'Lead updated successfully', 'lead' => $lead]);
    }


    // Store a new lead.
    // @param Request $request
    // @return \Illuminate\Http\JsonResponse

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name'         => self::REQUIRED_STRING,
            'last_name'          => self::REQUIRED_STRING,
            'email'              => 'required|string|email|max:255',
            'phone_number'       => 'required|regex:/^[6-9]\d{9}$/',
            'status'             => self::OPTIONAL_STRING,
            'organization_id'    => 'nullable|integer|exists:organizations,id',
            'organization_name'  => self::OPTIONAL_STRING,
            'organization_size'  => self::OPTIONAL_STRING,
            'referral_source_id' => 'nullable|integer|exists:referral_sources,id',
            'referral_other_text' => self::OPTIONAL_STRING,
            'find_us'            => self::OPTIONAL_STRING,
            'address_line_1'     => self::OPTIONAL_STRING,
            'address_line_2'     => self::OPTIONAL_STRING,
            'zip_code'           => self::OPTIONAL_STRING,
            'country_id'         => 'nullable|integer|exists:countries,id',
            'state_id'           => 'nullable|integer|exists:states,id',
            'city_id'            => 'nullable|integer|exists:cities,id',
            'create_organization' => 'nullable|boolean',
        ]);

        // Prepare lead data - only include fields that actually exist in leads table
        $leadData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'status' => $data['status'] ?? 'Lead Stage',
            'organization_id' => $data['organization_id'] ?? null,
        ];

        // Create the lead first
        $lead = Lead::create($leadData);

        // If lead was successfully created, THEN create organization if requested
        if ($lead && $request->has('create_organization') && $request->create_organization && !empty($data['organization_name'])) {
            try {
                $org = \App\Models\Organization::create([
                    'name' => $data['organization_name'],
                    'size' => $data['organization_size'] ?? null,
                    'referral_source_id' => $data['referral_source_id'] ?? null,
                    'referral_other_text' => $data['referral_other_text'] ?? null,
                ]);

                // Update the lead with the new organization_id
                $lead->organization_id = $org->id;
                $lead->save();

                // Create organization address if address data provided
                if (!empty($data['address_line_1']) || !empty($data['country_id'])) {
                    \App\Models\OrganizationAddress::create([
                        'organization_id' => $org->id,
                        'address_line_1' => $data['address_line_1'] ?? null,
                        'address_line_2' => $data['address_line_2'] ?? null,
                        'country_id' => $data['country_id'] ?? null,
                        'state_id' => $data['state_id'] ?? null,
                        'city_id' => $data['city_id'] ?? null,
                        'zip_code' => $data['zip_code'] ?? null,
                    ]);
                }

                Log::info('LeadController: Created organization and address from lead', [
                    'lead_id' => $lead->id,
                    'org_id' => $org->id,
                    'lead_email' => $data['email']
                ]);
            } catch (\Exception $e) {
                Log::error('LeadController: Failed to create organization from lead', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage()
                ]);
                // Lead is still created, just without organization
            }
        }

        // If a user already exists with this email, mark the lead as Registered.
        try {
            $userModel = '\App\\Models\\User';
            $matchedUser = $userModel::where('email', $lead->email)->first();
            if ($matchedUser) {
                $lead->status = 'Registered';
                $lead->save();
                Log::info('LeadController: Created lead matched existing user; marked Registered', [
                    'lead_id' => $lead->id,
                    'user_id' => $matchedUser->id
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('LeadController: Failed to check users table after lead create: ' . $e->getMessage());
        }

        return response()->json(['message' => 'Lead saved successfully', 'lead' => $lead], 201);
    }


    // List all leads for authenticated user.
    // Superadmin sees all leads.
    // @return \Illuminate\Http\JsonResponse

    public function index(): JsonResponse
    {
        $user = request()->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $with = [
            'notes.creator:id,first_name,last_name,email',
            'organization.user:id,first_name,last_name,email,phone_number',
            'organization:id,name,size'
                . ',contract_start,contract_end,referral_source_id,referral_other_text',
            'organization.referralSource',
            'organization.address',
        ];

        // Role-based visibility
        if (method_exists($user, 'hasRole') && ($user->hasRole('superadmin') || $user->hasRole('dolphinadmin') || $user->hasRole('salesperson'))) {
            // Admin-style roles can view all leads
            $leads = Lead::with($with)->get();
        } elseif (method_exists($user, 'hasRole') && $user->hasRole('organizationadmin')) {
            // Organization admin can see leads for their organization only
            $orgId = \App\Models\Organization::where('user_id', $user->id)->value('id');
            $leads = Lead::with($with)
                ->when(
                    $orgId,
                    function ($q) use ($orgId) {
                        return $q->where('organization_id', $orgId);
                    },
                    function ($q) {
                        return $q->whereRaw('1=0');
                    }
                )
                ->get();
        } elseif (Schema::hasColumn('leads', 'created_by')) {
            // Fallback: creator-scoped
            $leads = Lead::with($with)->where('created_by', $user->id)->get();
        } else {
            // No matching scope — return empty set
            $leads = collect();
        }

        $payload = $leads->map(function ($lead) {
            // Get organization data from relationship
            $org = $lead->organization;
            $orgAddress = $org?->address;

            return [
                'id' => $lead->id,
                'organization_id' => $lead->organization_id,
                'first_name' => $lead->first_name ?? null,
                'last_name' => $lead->last_name ?? null,
                'name' => $this->resolveDisplayName($lead),
                'contact' => $this->resolveDisplayName($lead),
                'email' => $lead->email,
                'phone_number' => $lead->phone_number,
                'status' => $lead->status,
                // Organization data from relationship
                'organization_name' => $org?->name ?? null,
                'organization_size' => $org?->size ?? null,
                'referral_source_id' => $org?->referral_source_id ?? null,
                'referral_source_name' => $org?->referralSource?->name ?? null,
                'referral_other_text' => $org?->referral_other_text ?? null,
                // Address data from organization address relationship
                'address_line_1' => $orgAddress?->address_line_1 ?? null,
                'address_line_2' => $orgAddress?->address_line_2 ?? null,
                'zip_code' => $orgAddress?->zip_code ?? null,
                'country_id' => $orgAddress?->country_id ?? null,
                'state_id' => $orgAddress?->state_id ?? null,
                'city_id' => $orgAddress?->city_id ?? null,
                'created_at' => $lead->created_at,
                'updated_at' => $lead->updated_at,
                'deleted_at' => $lead->deleted_at,
                'organization' => $org ? [
                    'id' => $org->id,
                    'name' => $org->name ?? null,
                    'size' => $org->size ?? null,
                    'contract_start' => $org->contract_start ?? null,
                    'contract_end' => $org->contract_end ?? null,
                ] : null,
                'notes_count' => $lead->notes ? $lead->notes->count() : 0,
            ];
        });

        return response()->json($payload);
    }


    // Show details for a single lead.
    // Includes registration link, organization, and sales person info if available.
    // @param int $id
    // @return \Illuminate\Http\JsonResponse

    public function show(int $id): JsonResponse
    {
        $lead = Lead::with([
            'notes.creator:id,first_name,last_name,email',
            'organization.user:id,first_name,last_name,email,phone_number',
            'organization.salesPerson:id,first_name,last_name,email',
            'organization.referralSource',
            'organization.address.country',
            'organization.address.state',
            'organization.address.city',
            'organization',
        ])->find($id);
        if (!$lead) {
            return response()->json(['message' => self::MESSAGE], 404);
        }
        $registration_link = $this->buildRegistrationLink($lead);
        Log::info('LeadController: prepared registration_link', [
            'registration_link' => $registration_link,
            'lead_id' => $lead->id,
        ]);

        $defaultTemplate = $this->buildDefaultTemplate($lead, $registration_link);

        // Prefer relationship-based organization if available
        $org = $lead->organization;
        $orgUser = $org && $org->user ? $org->user : null;
        $salesPerson = $org && $org->salesPerson ? $org->salesPerson : null;

        // Build referral source display
        $referralSource = 'N/A';
        if ($org && $org->referralSource) {
            $referralSource = $org->referralSource->name;
            // If referral source is "Other", append the custom text
            if (strtolower($referralSource) === 'other' && !empty($org->referral_other_text)) {
                $referralSource = 'Other: ' . $org->referral_other_text;
            }
        }

        // Build address display with address_line_2
        $address = null;
        try {
            if ($org && $org->address) {
                $addr = $org->address;
                $parts = [];

                if (!empty($addr->address_line_1)) {
                    $parts[] = $addr->address_line_1;
                }
                if (!empty($addr->address_line_2)) {
                    $parts[] = $addr->address_line_2;
                }
                if (isset($addr->city) && is_object($addr->city) && !empty($addr->city->name)) {
                    $parts[] = $addr->city->name;
                }
                if (isset($addr->state) && is_object($addr->state) && !empty($addr->state->name)) {
                    $parts[] = $addr->state->name;
                }
                if (!empty($addr->zip_code)) {
                    $parts[] = $addr->zip_code;
                }
                if (isset($addr->country) && is_object($addr->country) && !empty($addr->country->name)) {
                    $parts[] = $addr->country->name;
                }

                $address = implode(', ', array_filter($parts));
            }
        } catch (\Exception $e) {
            Log::warning('LeadController@show building address failed: ' . $e->getMessage());
        }

        $resp = [
            'lead' => $lead,
            'defaultTemplate' => $defaultTemplate,
        ];

        if ($org) {
            $orgAddress = $org->address;
            $resp['organization'] = [
                'id' => $org->id,
                'name' => $org->name ?? null,
                'size' => $org->size ?? null,
                'referral_source_id' => $org->referral_source_id ?? null,
                'referral_other_text' => $org->referral_other_text ?? null,
                'contract_start' => $org->contract_start ?? null,
                'contract_end' => $org->contract_end ?? null,
                'address_display' => $address ?? 'N/A',
                'referral_source' => $referralSource,
                'sales_person' => $salesPerson ? ($salesPerson->first_name . ' ' . $salesPerson->last_name) : 'N/A',
                'address' => $orgAddress ? [
                    'address_line_1' => $orgAddress->address_line_1 ?? null,
                    'address_line_2' => $orgAddress->address_line_2 ?? null,
                    'country_id' => $orgAddress->country_id ?? null,
                    'state_id' => $orgAddress->state_id ?? null,
                    'city_id' => $orgAddress->city_id ?? null,
                    'zip_code' => $orgAddress->zip_code ?? null,
                ] : null,
            ];
        }

        if ($orgUser) {
            $resp['orgUser'] = $orgUser;
        }

        return response()->json($resp);
    }

    /**
     * Build a registration link for a lead.
     */
    private function buildRegistrationLink(Lead $lead): string
    {
        $frontendBase = env('FRONTEND_URL', env('APP_URL', 'http://127.0.0.1:8080'));
        // Prefer canonical name and phone attributes where available.
        $queryParams = [
            'email' => $lead->email,
            // include a canonical 'name' along with first/last for backwards compatibility
            'name' => $this->resolveDisplayName($lead),
            'first_name' => $lead->first_name ?? null,
            'last_name' => $lead->last_name ?? null,
            'phone_number' => $lead->phone_number ?? '',
            'lead_id' => $lead->id,
        ];

        $base = rtrim($frontendBase, '/');
        $query = http_build_query($queryParams);
        return $base . '/register?' . $query;
    }

    /**
     * Build default registration email HTML template for a lead.
     */
    private function buildDefaultTemplate(Lead $lead, string $registrationLink): string
    {
        $safeLink = htmlspecialchars($registrationLink, ENT_QUOTES, 'UTF-8');
        // Prefer a canonical display name for the lead (name > first+last > email)
        $safeName = htmlspecialchars((string)$this->resolveDisplayName($lead), ENT_QUOTES, 'UTF-8');
        return <<<HTML
            <h2>Hello {$safeName},</h2>
            <p>You've been invited to complete your signup. Please click the button below to enter your details and activate your account.</p>
            <p style="text-align: center;">
                <a href="{$safeLink}"
                   style="display: inline-block; padding: 12px 24px; background-color: #0164A5;"
                   >
                    <span style="color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; display:inline-block; padding:6px 8px;">Complete Signup</span>
                </a>
            </p>
            <p style="font-size: 13px; color: #888888; text-align: center;">
                If you did not request this, you can safely ignore this email.
            </p>
        HTML;
    }

    /**
     * Resolve a friendly display name for a model.
     * Preference order: name, full_name, lead_name, first_name + last_name, email, id
     * @param  mixed  $model
     * @return string
     */
    private function resolveDisplayName($model): string
    {
        $result = '';
        if (!$model) {
            return $result;
        }

        // Try common canonical fields first
        if (!empty($model->name)) {
            $result = (string)$model->name;
        } elseif (!empty($model->full_name)) {
            $result = (string)$model->full_name;
        } elseif (!empty($model->lead_name)) {
            $result = (string)$model->lead_name;
        } else {
            // Use first/last if present
            $first = $model->first_name ?? null;
            $last = $model->last_name ?? null;
            if ($first || $last) {
                $result = trim((string)($first . ' ' . $last));
            } elseif (!empty($model->email)) {
                // Fallbacks
                $result = (string)$model->email;
            } elseif (!empty($model->id)) {
                $result = (string)$model->id;
            }
        }

        return $result;
    }


    // sales person resolution removed — application schema no longer guarantees sales_person_id on leads/orgs


    // Soft-delete a lead by id.
    // @param Request $request
    // @param int $id
    // @return \Illuminate\Http\JsonResponse

    public function destroy(Request $request, int $id): JsonResponse
    {
        $lead = Lead::find($id);
        if (!$lead) {
            return response()->json(['message' => self::MESSAGE], 404);
        }

        try {
            $lead->delete();
            Log::info('LeadController@destroy soft-deleted lead', [
                'lead_id'   => $id,
                'deleted_by' => $request->user()->id ?? null
            ]);
            return response()->json(['message' => 'Lead soft-deleted', 'id' => $id]);
        } catch (\Exception $e) {
            Log::error('LeadController@destroy failed to delete lead: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete lead'], 500);
        }
    }


    // Generates registration invite email HTML.
    // @param Request $request
    // @return \Illuminate\Http\Response

    public function leadRegistration(Request $request): Response
    {
        $registration_link = $request->query('registration_link', rtrim(env('FRONTEND_URL', env('APP_URL', 'http://127.0.0.1:8080')), '/') . '/register');
        $name = $request->query('name', '');

        $safeLink = htmlspecialchars($registration_link, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name ?: 'User', ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>Registration Invite</title>
        </head>
        <body>
            <div class="email-container">
                <div style="width:100%; padding:40px 0; background-color:#f6f9fc; font-family: Arial, sans-serif;">
                    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:6px; padding:30px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <div style="font-size:20px; font-weight:bold; color:#333333; margin-bottom:15px;">Hello {$safeName},</div>
                        <div style="font-size:16px; color:#555555; line-height:1.5; margin-bottom:25px;">You’ve been invited to complete your signup. Please click the button below to enter your details and activate your account.</div>
                        <div style="text-align:center;">
                            <a href="{$safeLink}"
                               style="display:inline-block; padding:10px 20px; background-color:#0164A5;">
                                <span style="color:#ffffff; text-decoration:none; border-radius:50px; font-weight:bold; display:inline-block; padding:6px 8px;">Complete Signup</span>
                            </a>
                        </div>
                        <div style="font-size:13px; color:#888888; text-align:center; margin-top:30px;">If you did not request this, you can safely ignore this email.</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }


    // Generates lead agreement email HTML.
    // @param Request $request
    // @return \Illuminate\Http\Response

    public function leadAgreement(Request $request): Response
    {
        $checkout_url = $request->query('checkout_url', rtrim(env('FRONTEND_URL', env('APP_URL', 'http://127.0.0.1:8080')), '/') . '/subscriptions/plans');
        $name = $request->query('name', '');

        $safeLink = htmlspecialchars($checkout_url, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name ?: 'User', ENT_QUOTES, 'UTF-8');

        Log::info('LeadController: prepared leadAgreement checkout_url', ['checkout_url' => $checkout_url, 'name' => $name]);

        $html = <<<HTML
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>Agreement and Payment</title>
        </head>
        <body>
            <div class="email-container">
                <div style="width:100%; padding:40px 0; background-color:#f6f9fc; font-family: Arial, sans-serif;">
                    <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:6px; padding:30px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                        <div style="font-size:20px; font-weight:bold; color:#333333; margin-bottom:15px;">Hello {$safeName},</div>
                        <div style="font-size:16px; color:#555555; line-height:1.5; margin-bottom:25px;">Please find your agreement and payment link below. Click the button to proceed with the subscription.</div>
                        <div style="text-align:center;">
                            <a href="{$safeLink}"
                               style="display:inline-block; padding:10px 20px; background-color:#0164A5;">
                                <span style="color:#ffffff; text-decoration:none; border-radius:50px; font-weight:bold; display:inline-block; padding:6px 8px;">Proceed to Payment</span>
                            </a>
                        </div>
                        <div style="font-size:13px; color:#888888; text-align:center; margin-top:30px;">If you did not request this, you can safely ignore this email.</div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }


    // Prefill lead info for registration form.
    // @param Request $request
    // @return \Illuminate\Http\JsonResponse

    public function prefill(Request $request): JsonResponse
    {
        $lead = null;
        if ($request->has('lead_id')) {
            $lead = Lead::with([
                'organization.address.country',
                'organization.address.state',
                'organization.address.city',
                'organization.referralSource'
            ])->find($request->input('lead_id'));
        } elseif ($request->has('email')) {
            $lead = Lead::with([
                'organization.address.country',
                'organization.address.state',
                'organization.address.city',
                'organization.referralSource'
            ])->where('email', $request->input('email'))->first();
        }
        if (!$lead) {
            return response()->json(['message' => self::MESSAGE], 404);
        }

        // Get organization data
        $org = $lead->organization;
        $orgAddress = $org?->address;

        return response()->json(['lead' => [
            'organization_id'        => $lead->organization_id ?? null,
            'first_name'             => $lead->first_name ?? null,
            'last_name'              => $lead->last_name ?? null,
            'name'                   => $this->resolveDisplayName($lead),
            'email'                  => $lead->email,
            'phone_number'           => $lead->phone_number ?? null,
            'phone'                  => $lead->phone_number ?? null,
            'status'                 => $lead->status ?? null,
            // Organization data
            'organization_name'      => $org?->name ?? null,
            'organization_size'      => $org?->size ?? null,
            'referral_source_id'     => $org?->referral_source_id ?? null,
            'referral_other_text'    => $org?->referral_other_text ?? null,
            // Address data
            'organization_address'   => $orgAddress?->address_line_1 ?? null,
            'address_line_1'         => $orgAddress?->address_line_1 ?? null,
            'address_line_2'         => $orgAddress?->address_line_2 ?? null,
            'country_id'             => $orgAddress?->country_id ?? null,
            'organization_state_id'  => $orgAddress?->state_id ?? null,
            'state_id'               => $orgAddress?->state_id ?? null,
            'organization_city_id'   => $orgAddress?->city_id ?? null,
            'city_id'                => $orgAddress?->city_id ?? null,
            'organization_zip'       => $orgAddress?->zip_code ?? null,
            'zip_code'               => $orgAddress?->zip_code ?? null,
        ]]);
    }


    // Get referral sources from referral_sources table.
    // @return \Illuminate\Http\JsonResponse

    public function findUsOptions(): JsonResponse
    {
        // Preserve ordering by numeric id as requested (instead of alphabetical name)
        $sources = \App\Models\ReferralSource::orderBy('id')->get(['id', 'name']);

        if ($sources->isEmpty()) {
            Log::info('Referral sources: none found');
        } else {
            Log::info('Referral sources fetched: ' . $sources->count());
        }

        return response()->json(['options' => $sources]);
    }
}
