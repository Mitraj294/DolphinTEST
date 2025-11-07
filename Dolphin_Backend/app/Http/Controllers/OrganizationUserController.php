<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organization;
use App\Models\Group;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Controller for managing users within an organization's groups
 * Replaces the old MemberController functionality
 */
class OrganizationUserController extends Controller
{
    /**
     * Get all organization members from organization_member table
     * These are users that have been added as members to the organization
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Get members from organization_member pivot table
            $members = $organization->members()
                ->with(['roles', 'groups' => function ($query) use ($orgId) {
                    $query->where('organization_id', $orgId);
                }])
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'first_name' => $member->first_name,
                        'last_name' => $member->last_name,
                        'email' => $member->email,
                        'phone' => $member->phone ?? null,
                        'roles' => $member->roles->pluck('name'),
                        'groups' => $member->groups->map(function ($group) {
                            return [
                                'id' => $group->id,
                                'name' => $group->name,
                            ];
                        }),
                    ];
                });

            return response()->json(['data' => $members]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve organization members.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available users to add as organization members
     * Returns users with 'user' or 'salesperson' roles who are NOT already members
     */
    public function availableUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Get existing member IDs
            $existingMemberIds = $organization->members()->pluck('users.id')->toArray();

            // Get users with 'user' or 'salesperson' roles who are not already members
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['user', 'salesperson']);
            })
                ->whereNotIn('id', $existingMemberIds)
                ->select('id', 'first_name', 'last_name', 'email')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                    ];
                });

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve available users.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get available members (from organization_member) to add to groups
     * Returns members of the organization only
     */
    public function getAvailableMembersForGroup(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Get organization members from organization_member table
            $members = $organization->members()
                ->select('users.id', 'users.first_name', 'users.last_name', 'users.email')
                ->get()
                ->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => trim("{$member->first_name} {$member->last_name}"),
                        'email' => $member->email,
                    ];
                });

            return response()->json($members);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve members for group.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Add a user to a group
     */
    public function addToGroup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
                'role' => 'nullable|string',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            // Verify group belongs to organization
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            // Verify user belongs to organization
            $userToAdd = User::where('id', $validated['user_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            // Check user doesn't have admin role
            if ($userToAdd->roles()->whereIn('name', ['superadmin', 'dolphin_admin', 'organizationadmin', 'salesperson'])->exists()) {
                return response()->json(['error' => 'Cannot add admin users to groups as members.'], 400);
            }

            // Add user to group
            $group->users()->syncWithoutDetaching([
                $validated['user_id'] => ['role' => $validated['role'] ?? 'member']
            ]);

            return response()->json([
                'message' => 'User added to group successfully.',
                'group' => $group->load('users')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add user to group.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove a user from a group
     */
    public function removeFromGroup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            // Verify group belongs to organization
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            // Remove user from group
            $group->users()->detach($validated['user_id']);

            return response()->json([
                'message' => 'User removed from group successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove user from group.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Update user's role in a group
     */
    public function updateGroupRole(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
                'role' => 'required|string',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            // Verify group belongs to organization
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            // Update role in pivot table
            $group->users()->updateExistingPivot($validated['user_id'], [
                'role' => $validated['role']
            ]);

            return response()->json([
                'message' => 'User role updated successfully.',
                'group' => $group->load('users')
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update user role.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Add user to organization via organization_users pivot (with status tracking)
     */
    public function addOrganizationUser(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'status' => 'sometimes|in:active,inactive',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Add user to organization with status
            $organization->organizationUsers()->syncWithoutDetaching([
                $validated['user_id'] => ['status' => $validated['status'] ?? 'active']
            ]);

            return response()->json([
                'message' => 'User added to organization successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add user to organization.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Add user to organization as member via organization_member pivot
     * Users must have 'user' or 'salesperson' role
     */
    public function addOrganizationMember(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Verify user has 'user' or 'salesperson' role
            $userToAdd = User::findOrFail($validated['user_id']);
            $hasValidRole = $userToAdd->roles()
                ->whereIn('name', ['user', 'salesperson'])
                ->exists();

            if (!$hasValidRole) {
                return response()->json([
                    'error' => 'User must have "user" or "salesperson" role to be added as organization member.'
                ], 400);
            }

            // Add user as organization member
            $organization->members()->syncWithoutDetaching($validated['user_id']);

            // Return updated member with details
            $member = User::with(['roles'])
                ->find($validated['user_id']);

            return response()->json([
                'message' => 'Member added to organization successfully.',
                'member' => [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone ?? null,
                    'roles' => $member->roles->pluck('name'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add organization member.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Remove user from organization members
     */
    public function removeOrganizationMember(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            // Remove user from organization members
            $organization->members()->detach($validated['user_id']);

            return response()->json([
                'message' => 'Member removed from organization successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove organization member.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get all organization users (via organization_users pivot)
     */
    public function getOrganizationUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);
            $users = $organization->organizationUsers()
                ->withPivot('status')
                ->get();

            return response()->json(['data' => $users]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve organization users.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get all organization members (via organization_member pivot)
     */
    public function getOrganizationMembers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);
            $members = $organization->members()->get();

            return response()->json(['data' => $members]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve organization members.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Get the organization ID for the currently authenticated user.
     */
    private function getOrganizationIdForCurrentUser(\App\Models\User $user): int
    {
        $orgId = $user->organization_id;

        if (!$orgId) {
            $organization = Organization::where('user_id', $user->id)->first();
            $orgId = $organization ? $organization->id : null;
        }

        if (!$orgId) {
            throw new \Exception('Organization not found for user.');
        }

        return $orgId;
    }
}
