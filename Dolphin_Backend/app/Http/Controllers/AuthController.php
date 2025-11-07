<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Models\UserDetail;
use App\Models\Organization;
use App\Models\Role;
use App\Models\Lead;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use App\Services\AuthUserService;

class AuthController extends Controller
{
    // Register a new user, their details, and organization.

    public function register(RegisterRequest $request)
    {
        try {
            $service = new AuthUserService();
            $user = $service->createUserAndDetails($request->validated());
            $service->updateLeadStatus($user->email);

            return response()->json(['message' => 'User registered successfully', 'user' => $user], 201);
        } catch (\Exception $e) {
            Log::error('User registration failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'An unexpected error occurred during registration.'], 500);
        }
    }

    //Authenticate a user and issue a Passport token.

    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $tokenResponse = $this->issueToken($request);
        if ($tokenResponse->getStatusCode() >= 400) {
            return $tokenResponse;
        }

        $tokenData = json_decode($tokenResponse->getContent(), true);
        // Update organization's last_contacted when an organization admin logs in
        try {
            $isOrgAdmin = $user->roles()->where('name', 'organizationadmin')->exists();
            if ($isOrgAdmin) {
                Organization::where('user_id', $user->id)->update(['last_contacted' => now()]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to update organization last_contacted on login', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        // Rebuild user payload after potential org update
        $service = new AuthUserService();
        $userPayload = $service->buildUserPayload($user->fresh());

        return response()->json([
            'message'       => 'Login successful',
            'access_token'  => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'expires_in'    => $tokenData['expires_in'],
            'user'          => $userPayload,
        ]);
    }

    //Get the authenticated user's profile information.

    public function profile(Request $request)
    {
        $userPayload = (new AuthUserService())->buildUserPayload($request->user());
        return response()->json($userPayload);
    }

    //Update the authenticated user's profile.

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        DB::beginTransaction();
        try {
            $validated = $request->validated();

            $service = new AuthUserService();
            $service->updateUserRecord($user, $validated['user'] ?? [], $validated['user_details'] ?? []);
            $service->updateUserDetailsRecord($user, $validated['user_details'] ?? []);

            DB::commit();

            return response()->json([
                'message' => 'Profile updated successfully',
                'user' => $service->buildUserPayload($user->fresh())
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update profile', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to update profile'], 500);
        }
    }


    //Change the authenticated user's password.

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['error' => 'Current password is incorrect'], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully']);
    }

    // Send a password reset link to the user.

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    // Reset the user's password.

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->password = Hash::make($password);
            $user->save();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    // Soft delete the authenticated user's account.

    public function deleteAccount(Request $request)
    {
        $request->user()->delete();
        return response()->json(['message' => 'Account deleted successfully']);
    }

    // Get the currently authenticated user. Alias for profile().

    public function user(Request $request)
    {
        return $this->profile($request);
    }

    // Log the user out (Revoke the token).

    public function logout(Request $request)
    {
        $request->user()->token()->revoke();
        return response()->json(['message' => 'Successfully logged out']);
    }

    // Check the validity and expiration of the current token.

    public function tokenStatus(Request $request)
    {
        $token = $request->user()->token();
        return response()->json([
            'valid' => true,
            'expires_at' => $token->expires_at,
        ]);
    }

    // Private Helper Methods
    // Helper methods related to token issuance remain here; user-related helpers moved to AuthUserService.


    private function issueToken(Request $request)

    {
        // Resolve client id/secret (prefer env, fall back to DB). Helpers keep logic small.
        [$clientId, $clientSecret] = $this->resolveClientCredentials();

        if (empty($clientId) || empty($clientSecret)) {
            Log::error('Password grant client id/secret missing or unusable. Cannot issue OAuth token.', ['client_id' => $clientId]);

            // Provide an actionable error to the operator so they can fix deployment config.
            return response()->json([
                'error' => 'server_error',
                'error_description' => 'OAuth password client_id or client_secret not available. Set PASSPORT_PASSWORD_CLIENT_ID and PASSPORT_PASSWORD_CLIENT_SECRET in environment or recreate a password grant client with a known secret.'
            ], 500);
        }

        $proxy = Request::create('/oauth/token', 'POST', [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $request->email,
            'password' => $request->password,
            'scope' => '',
        ]);

        $response = app()->handle($proxy);

        if ($response->getStatusCode() >= 400) {
            Log::error('OAuth token dispatch failed.', [
                'status' => $response->getStatusCode(),
                'response' => $response->getContent()
            ]);
        }

        return $response;
    }

    /**
     * Helpers to resolve OAuth password client id/secret.
     */
    private function isBcryptHash(?string $val): bool
    {
        return !empty($val) && is_string($val) && preg_match('/^\$2[aby]\$/', $val);
    }

    private function fetchPasswordClientFromDb(): ?object
    {
        try {
            return DB::table('oauth_clients')
                ->whereJsonContains('grant_types', 'password')
                ->orWhereRaw("JSON_CONTAINS(grant_types, '\"password\"')")
                ->limit(1)
                ->first();
        } catch (\Exception $e) {
            Log::warning('Failed to lookup oauth password client from DB', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function resolveClientCredentials(): array
    {
        $clientId = config('passport.password_client_id');
        $clientSecret = config('passport.password_client_secret');

        // If env-provided secret looks like a bcrypt hash, do not use it as plaintext.
        if ($this->isBcryptHash($clientSecret)) {
            Log::warning('PASSPORT_PASSWORD_CLIENT_SECRET appears to be a hashed value; ignoring env secret.', ['client_id' => $clientId]);
            $clientSecret = null;
        }

        // If both provided via config, return early
        if (!empty($clientId) && !empty($clientSecret)) {
            return [$clientId, $clientSecret];
        }

        $row = $this->fetchPasswordClientFromDb();
        if (empty($row)) {
            return [$clientId, $clientSecret];
        }

        $clientId = $clientId ?: $row->id;
        if (empty($clientSecret) && !empty($row->secret)) {
            if ($this->isBcryptHash($row->secret)) {
                Log::warning('Found hashed oauth client secret in DB; cannot use as plaintext for token requests.', ['client_id' => $row->id]);
            } else {
                $clientSecret = $row->secret;
            }
        }

        return [$clientId, $clientSecret];
    }
}
