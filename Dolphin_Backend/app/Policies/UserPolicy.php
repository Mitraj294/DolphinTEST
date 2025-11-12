<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    
    public function impersonate(User $user, User $targetUser): bool
    {
        
        
        $isRequesterSuperAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('superadmin')
            : false;
        $isTargetSuperAdmin = method_exists($targetUser, 'hasRole')
            ? $targetUser->hasRole('superadmin')
            : false;

        return $isRequesterSuperAdmin && !$isTargetSuperAdmin && $user->id !== $targetUser->id;
    }
}
