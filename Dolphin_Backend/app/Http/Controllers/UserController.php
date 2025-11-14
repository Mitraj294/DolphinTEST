<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewUserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['country', 'roles'])->get()->map(function ($user) {
            return $this->formatUserPayload($user);
        });

        return response()->json(['users' => $users]);
    }



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


            try {


                $broker = Password::broker();
                $token = $broker->createToken($user);

                $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);
            } catch (\Exception $e) {

                $resetUrl = null;
            }


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



    public function updateRole(Request $request, User $user)
    {



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

                $rules['email'] = ['sometimes', 'email'];
            }
        }

        $validatedData = $request->validate($rules);



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





            try {
                $newRole = $validatedData['role'] ?? null;
                if ($oldRoleName === 'organizationadmin' && $newRole !== 'organizationadmin') {
                    Organization::where('user_id', $user->id)->update(['last_contacted' => now()]);
                }
            } catch (\Exception $e) {

                Log::warning('Failed to update organization last_contacted after role change', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            return response()->json($this->formatUserPayload($user->fresh()));
        } catch (\Exception $e) {
            Log::error('Error updating user role', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'An error occurred while updating the user.'], 500);
        }
    }


    public function softDelete(User $user)
    {
        try {
            $user->delete();

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


    public function impersonate(Request $request, User $user)
    {

        if ($request->user()->cannot('impersonate', $user)) {
            Log::warning('Unauthorized impersonation attempt', ['actor_id' => $request->user()?->id, 'target_id' => $user->id]);
            return response()->json(['message' => 'Unauthorized.'], 403);
        }


        Log::info('Impersonation attempt started', ['actor_id' => $request->user()?->id, 'target_id' => $user->id]);

        try {

            Log::debug('Creating impersonation token', ['target_user_id' => $user->id]);
            try {
                $tokenResult = $user->createToken('ImpersonationToken', ['impersonate']);
            } catch (\Exception $e) {

                Log::warning('createToken threw exception during impersonation', ['target_user_id' => $user->id, 'error' => $e->getMessage()]);
                if (str_contains($e->getMessage(), 'Personal access client not found')) {
                    Log::warning('Personal access client missing — attempting to create DB client rows', ['error' => $e->getMessage()]);
                    try {

                        $pacExists = false;
                        try {
                            $pacExists = \Illuminate\Support\Facades\DB::table('oauth_personal_access_clients')->exists();
                        } catch (\Exception $dbEx) {
                            Log::warning('Could not query oauth_personal_access_clients table', ['error' => $dbEx->getMessage()]);
                            $pacExists = false;
                        }

                        if (!$pacExists) {

                            $secret = \Illuminate\Support\Str::random(40);
                            $clientId = null;
                            try {

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
                        throw $e;
                    }


                    $tokenResult = $user->createToken('ImpersonationToken', ['impersonate']);
                } else {
                    throw $e;
                }
            }


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

            $logContext = [
                'actor_id' => $request->user()?->id,
                'target_user_id' => $user->id,
                'error_message' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ];


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


            $responseMsg = config('app.debug') ? $e->getMessage() : 'Failed to impersonate user.';
            return response()->json(['message' => $responseMsg], 500);
        }
    }





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
