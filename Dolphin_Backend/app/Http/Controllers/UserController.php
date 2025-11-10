<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use App\Notifications\NewUserInvitation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class UserController extends Controller
{
    //Display a listing of all users.

    public function index()
    {
        $users = User::with(['country', 'roles'])->get()->map(function ($user) {
            return $this->formatUserPayload($user);
        });

        return response()->json(['users' => $users]);
    }

    //Store a new user in the database.

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'email' => 'required|email|unique:users,email',
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone_number' => 'required|regex:/^[6-9]\d{9}$/',
                'role' => ['required', Rule::in(['user', 'organizationadmin', 'dolphinadmin', 'superadmin', 'salesperson'])],
                'name' => 'nullable|string|max:255|required_if:role,organizationadmin',
                'size' => 'nullable|string|max:255|required_if:role,organizationadmin',
            ]);

            $plainPassword = Str::random(12);
            $validatedData['password'] = Hash::make($plainPassword);

            $user = $this->createUserWithRelations($validatedData);

            // Generate a password reset token so the user can set their own password securely.
            try {
                // Help static analyzers (intelephense) infer the broker type so createToken() is recognized
                /** @var \Illuminate\Auth\Passwords\PasswordBroker $broker */
                $broker = Password::broker();
                $token = $broker->createToken($user);
                // Use named web route to redirect to frontend reset page (web.php redirects to frontend)
                $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);
            } catch (\Exception $e) {
                // Fallback: no reset link available
                $resetUrl = null;
            }

            // Send email notification with the temporary password and reset link (if available)
            try {
                $user->notify(new NewUserInvitation($plainPassword, $resetUrl));
            } catch (\Exception $e) {
                Log::warning('Failed to send new user invitation email', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'message' => 'User created successfully',
                'user' => $this->formatUserPayload($user->load('roles', 'country')),
                'password' => $plainPassword,
            ], 201);
        } catch (ValidationException $e) {
            Log::error('User creation validation failed:', ['errors' => $e->errors()]);
            return response()->json(['message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error creating user:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while creating the user.'], 500);
        }
    }

    //Update the specified user's role and basic information.

    public function updateRole(Request $request, User $user)
    {
        // Build rules dynamically so we only apply the unique rule when the
        // provided email is different from the current one. This avoids a
        // validation failure when the client resubmits the same email.
        $rules = [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|regex:/^[6-9]\d{9}$/',
            'role' => ['required', 'string', Rule::in(['user', 'organizationadmin', 'dolphinadmin', 'superadmin', 'salesperson'])],
            'name' => 'nullable|string|max:255',
            'size' => 'nullable|string|max:255',
        ];

        if ($request->has('email')) {
            $incomingEmail = $request->input('email');
            if ($incomingEmail && $incomingEmail !== $user->email) {
                $rules['email'] = ['sometimes', 'email', Rule::unique('users', 'email')];
            } else {
                // If email is the same as current, only validate format.
                $rules['email'] = ['sometimes', 'email'];
            }
        }

        $validatedData = $request->validate($rules);

    // Capture the user's current primary role (if any) so we can detect
    // transitions (e.g. organizationadmin -> user) and react accordingly.
    $oldRole = $user->roles()->first();
    $oldRoleName = $oldRole->name ?? null;

        try {
            DB::transaction(function () use ($user, $validatedData, $request) {
                $user->update($validatedData);

                $role = Role::where('name', $validatedData['role'])->first();
                if ($role) {
                    $user->roles()->sync([$role->id]);
                } else {
                    Log::warning('Requested role not found during updateRole', ['role' => $validatedData['role'], 'user_id' => $user->id]);
                }

                if ($request->has('name')) {
                    Organization::updateOrCreate(
                        ['user_id' => $user->id],
                        [
                            'name' => $validatedData['name'],
                            'size' => $validatedData['size'] ?? null,
                        ]
                    );
                }
            });

            // After the DB transaction completes, if the user USED to be an
            // organizationadmin but is no longer one, mark their organization
            // with a last_contacted timestamp so we have a record of the
            // moment the admin relationship changed.
            try {
                $newRole = $validatedData['role'] ?? null;
                if ($oldRoleName === 'organizationadmin' && $newRole !== 'organizationadmin') {
                    Organization::where('user_id', $user->id)->update(['last_contacted' => now()]);
                }
            } catch (\Exception $e) {
                // Log but don't fail the API call
                Log::warning('Failed to update organization last_contacted after role change', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            return response()->json($this->formatUserPayload($user->fresh()));
        } catch (\Exception $e) {
            Log::error('Error updating user role', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while updating the user.'], 500);
        }
    }

    //Soft delete a user.
    public function softDelete(User $user)
    {
        try {
            $user->delete();
            // Ensure deleted_at is populated; sometimes DB-level issues can prevent Eloquent from setting it
            if (is_null($user->deleted_at)) {
                $user->deleted_at = now();
                $user->save();
            }
            return response()->json(['message' => 'User soft deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('Error soft deleting user', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error soft deleting user'], 500);
        }
    }

    // Impersonate another user (superadmin only)
    public function impersonate(Request $request, User $user)
    {
        // A policy should handle this authorization check
        if ($request->user()->cannot('impersonate', $user)) {
            Log::warning('Unauthorized impersonation attempt', ['actor_id' => $request->user()?->id, 'target_id' => $user->id]);
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Log start of impersonation attempt for easier debugging
        Log::info('Impersonation attempt started', ['actor_id' => $request->user()?->id, 'target_id' => $user->id]);

        try {
            // Attempt to create a personal access token for impersonation.
            Log::debug('Creating impersonation token', ['target_user_id' => $user->id]);
            try {
                $tokenResult = $user->createToken('ImpersonationToken', ['impersonate']);
            } catch (\Exception $e) {
                // Detect common Passport missing-client error and attempt an automatic fix
                Log::warning('createToken threw exception during impersonation', ['target_user_id' => $user->id, 'error' => $e->getMessage()]);
                if (str_contains($e->getMessage(), 'Personal access client not found')) {
                    Log::warning('Personal access client missing — attempting to create DB client rows', ['error' => $e->getMessage()]);
                    try {
                        // Check if oauth_personal_access_clients exists and has rows
                        $pacExists = false;
                        try {
                            $pacExists = \Illuminate\Support\Facades\DB::table('oauth_personal_access_clients')->exists();
                        } catch (\Exception $dbEx) {
                            Log::warning('Could not query oauth_personal_access_clients table', ['error' => $dbEx->getMessage()]);
                            $pacExists = false;
                        }

                        if (!$pacExists) {
                            // Create an oauth_clients row and corresponding personal access client entry.
                            $secret = \Illuminate\Support\Str::random(40);
                            $clientId = null;
                            try {
                                // Build insert payload based on actual oauth_clients columns to avoid schema mismatch
                                $cols = Schema::getColumnListing('oauth_clients');
                                $payload = [];
                                if (in_array('id', $cols)) {
                                    $uuid = (string) \Illuminate\Support\Str::uuid();
                                    $payload['id'] = $uuid;
                                }
                                if (in_array('owner_type', $cols)) {
                                    $payload['owner_type'] = null;
                                }
                                if (in_array('owner_id', $cols)) {
                                    $payload['owner_id'] = null;
                                }
                                if (in_array('name', $cols)) {
                                    $payload['name'] = 'Personal Access Client';
                                }
                                if (in_array('secret', $cols)) {
                                    $payload['secret'] = $secret;
                                }
                                if (in_array('provider', $cols)) {
                                    $payload['provider'] = null;
                                }
                                if (in_array('redirect_uris', $cols)) {
                                    $payload['redirect_uris'] = json_encode([]);
                                }
                                if (in_array('grant_types', $cols)) {
                                    $payload['grant_types'] = json_encode(['personal_access']);
                                }
                                if (in_array('revoked', $cols)) {
                                    $payload['revoked'] = 0;
                                }
                                if (in_array('created_at', $cols)) {
                                    $payload['created_at'] = now();
                                }
                                if (in_array('updated_at', $cols)) {
                                    $payload['updated_at'] = now();
                                }

                                // Insert using DB::table->insert (works with uuid PKs)
                                \Illuminate\Support\Facades\DB::table('oauth_clients')->insert($payload);
                                $clientId = $payload['id'] ?? null;
                                Log::info('Inserted oauth_clients personal access client', ['client_id' => $clientId, 'payload_keys' => array_keys($payload)]);
                            } catch (\Exception $insertEx) {
                                Log::warning('Failed to insert oauth_clients row', ['error' => $insertEx->getMessage()]);
                                $clientId = null;
                            }

                            if ($clientId) {
                                try {
                                    if (Schema::hasTable('oauth_personal_access_clients')) {
                                        \Illuminate\Support\Facades\DB::table('oauth_personal_access_clients')->insert(['client_id' => $clientId]);
                                        Log::info('Inserted oauth_personal_access_clients entry', ['client_id' => $clientId]);
                                    } else {
                                        Log::warning('oauth_personal_access_clients table missing; cannot insert personal access mapping', []);
                                    }
                                } catch (\Exception $insertPacEx) {
                                    Log::warning('Failed to insert oauth_personal_access_clients row', ['error' => $insertPacEx->getMessage()]);
                                }
                            }
                        }
                    } catch (\Exception $ae) {
                        Log::error('Failed to create personal access client rows during impersonation fallback', ['error' => $ae->getMessage()]);
                        throw $e; // rethrow original
                    }

                    // Retry token creation once
                    $tokenResult = $user->createToken('ImpersonationToken', ['impersonate']);
                } else {
                    throw $e;
                }
            }

            // Set expiry and persist token model when available
            if (isset($tokenResult->token)) {
                $tokenResult->token->expires_at = now()->addHours(1);
                $tokenResult->token->save();
            }

            $accessTokenString = $tokenResult->accessToken ?? ($tokenResult->access_token ?? null);
            $expiresAtStr = $tokenResult->token?->expires_at?->toIso8601String() ?? null;

            Log::info('Impersonation token created', ['target_user_id' => $user->id, 'has_token' => (bool)$accessTokenString]);

            return response()->json([
                'message' => "Successfully impersonating {$user->first_name}.",
                'impersonated_token' => $accessTokenString,
                'user' => $this->formatUserPayload($user),
                'expires_at' => $expiresAtStr,
            ]);
        } catch (\Exception $e) {
            // Log detailed error including stack trace to aid debugging
            $logContext = [
                'actor_id' => $request->user()?->id,
                'target_user_id' => $user->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ];

            // If this is the missing personal access client error, capture schema diagnostics
            if (str_contains($e->getMessage(), 'Personal access client not found')) {
                try {
                    $oauthClientsExists = Schema::hasTable('oauth_clients');
                    $oauthPacExists = Schema::hasTable('oauth_personal_access_clients');
                    $oauthClientsCols = $oauthClientsExists ? Schema::getColumnListing('oauth_clients') : [];

                    $logContext['oauth_clients_table'] = $oauthClientsExists;
                    $logContext['oauth_personal_access_clients_table'] = $oauthPacExists;
                    $logContext['oauth_clients_columns'] = $oauthClientsCols;
                } catch (\Exception $diagEx) {
                    $logContext['schema_diag_error'] = $diagEx->getMessage();
                }
            }

            Log::error('Impersonation failed', $logContext);

            // Construct a helpful response
            if (str_contains($e->getMessage(), 'Personal access client not found')) {
                $diagnostic = [];
                try {
                    $diagnostic['oauth_clients_table'] = Schema::hasTable('oauth_clients');
                    $diagnostic['oauth_personal_access_clients_table'] = Schema::hasTable('oauth_personal_access_clients');
                    if ($diagnostic['oauth_clients_table']) {
                        $diagnostic['oauth_clients_columns'] = Schema::getColumnListing('oauth_clients');
                    }
                } catch (\Exception $diagEx) {
                    $diagnostic['schema_diag_error'] = $diagEx->getMessage();
                }

                $message = 'Impersonation failed because Passport personal access client is missing. See logs for diagnostics.';
                $details = config('app.debug') ? ['diagnostic' => $diagnostic, 'error' => $e->getMessage()] : null;
                return response()->json(['message' => $message, 'details' => $details], 500);
            }

            // Generic fallback
            $responseMsg = config('app.debug') ? $e->getMessage() : 'Failed to impersonate user.';
            return response()->json(['message' => $responseMsg], 500);
        }
    }

    // Private Helper Methods

    // Create a user and their related models within a database transaction.

    private function createUserWithRelations(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create($data);

            if ($data['role'] === 'organizationadmin') {
                $org = Organization::create([
                    'user_id' => $user->id,
                    'name' => $data['name'] ?? 'Organization',
                    'size' => $data['size'] ?? null,
                ]);

                // Persist organization_id back to user
                $user->organization_id = $org->id;
                $user->save();
            }

            $role = Role::where('name', $data['role'])->first();
            if ($role) {
                $user->roles()->attach($role);
            } else {
                Log::warning('Requested role not found during createUserWithRelations', ['role' => $data['role'] ?? null]);
            }

            return $user;
        });
    }

    //Format the user data into a consistent payload for API responses.

    private function formatUserPayload(User $user): array
    {
        $fullName = trim($user->first_name . ' ' . $user->last_name);
        
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'name' => $fullName ?: $user->email,
            'role' => $user->roles->first()->name ?? 'user',
            'phone_number' => $user->phone_number ?? null,
            'country' => $user->country->name ?? null,
            'country_id' => $user->country_id ?? null,
        ];
    }
}
