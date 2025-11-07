<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine if the given user can impersonate another user.
     *
     * @param  \App\Models\User  $user      The currently authenticated user
     * @param  \App\Models\User  $targetUser The user being impersonated
     * @return bool
     */
    public function impersonate(User $user, User $targetUser): bool
    {
        // A superadmin can impersonate any user, except another superadmin or themselves.
        // Use role helper from HasRoles trait to avoid undefined method errors.
        $isRequesterSuperAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('superadmin')
            : false;
        $isTargetSuperAdmin = method_exists($targetUser, 'hasRole')
            ? $targetUser->hasRole('superadmin')
            : false;

        return $isRequesterSuperAdmin && !$isTargetSuperAdmin && $user->id !== $targetUser->id;
    }
}
