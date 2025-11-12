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

class OrganizationUserController extends Controller
{
    
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            
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
                            'phone' => $member->phone_number ?? null,
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

    
    public function availableUsers(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            
            $existingMemberIds = $organization->members()->pluck('users.id')->toArray();

            
            $users = User::whereHas('roles', function ($query) {
                $query->where('name', 'user');
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

    
    public function getAvailableMembersForGroup(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            
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

    
    public function addToGroup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
                'role' => 'nullable|string',
            ]);

            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            
            $userToAdd = User::where('id', $validated['user_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            
            if ($userToAdd->roles()->whereIn('name', ['superadmin', 'dolphin_admin', 'organizationadmin', 'salesperson'])->exists()) {
                return response()->json(['error' => 'Cannot add admin users to groups as members.'], 400);
            }

            
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

    
    public function removeFromGroup(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
            ]);

            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            
            $group->users()->detach($validated['user_id']);

            return response()->json([
                'message' => 'User removed from group successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove user from group.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    
    public function updateGroupRole(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'group_id' => 'required|exists:groups,id',
                'role' => 'required|string',
            ]);

            $user = $request->user();
            if (! $user) {
                return response()->json(['error' => 'Unauthenticated.'], 401);
            }
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            
            $group = Group::where('id', $validated['group_id'])
                ->where('organization_id', $orgId)
                ->firstOrFail();

            
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

    
    

    
    public function addOrganizationMember(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            
            $userToAdd = User::findOrFail($validated['user_id']);
            $hasValidRole = $userToAdd->roles()
                ->where('name', 'user')
                ->exists();

            if (!$hasValidRole) {
                return response()->json([
                    'error' => 'User must have the "user" role to be added as organization member.'
                ], 400);
            }

            
            $organization->members()->syncWithoutDetaching($validated['user_id']);

            
            $member = User::with(['roles'])
                ->find($validated['user_id']);

            return response()->json([
                'message' => 'Member added to organization successfully.',
                'member' => [
                    'id' => $member->id,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'email' => $member->email,
                    'phone' => $member->phone_number ?? null,
                    'roles' => $member->roles->pluck('name'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to add organization member.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    
    public function removeOrganizationMember(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);

            $user = $request->user();
            $orgId = $this->getOrganizationIdForCurrentUser($user);

            $organization = Organization::findOrFail($orgId);

            
            $organization->members()->detach($validated['user_id']);

            return response()->json([
                'message' => 'Member removed from organization successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove organization member.', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    
    

    
    

    
    private function getOrganizationIdForCurrentUser(\App\Models\User $user): int
    {
        
        $orgId = $user->organization_id ?? null;

        
        if (! $orgId) {
            try {
                $membership = $user->organizationMemberships()->first();
                if ($membership) {
                    $orgId = $membership->id;
                }
            } catch (\Throwable $e) {
                    // Log the failure to inspect memberships — fallback logic will continue
                    try {
                        Log::warning('[OrganizationUserController] failed to read organizationMemberships', ['error' => $e->getMessage()]);
                    } catch (\Throwable $_) {
                        // ignore logging failures
                    }
            }
        }

        
        if (! $orgId) {
            $organization = Organization::where('user_id', $user->id)->first();
            $orgId = $organization ? $organization->id : null;
        }

        
        if (! $orgId && request()->user() && request()->user()->roles()->where('name', 'superadmin')->exists()) {
            $orgId = (int) request()->query('organization_id', 0) ?: null;
        }

        if (! $orgId) {
            throw new \Exception('Organization not found for user.');
        }

        return (int) $orgId;
    }
}
