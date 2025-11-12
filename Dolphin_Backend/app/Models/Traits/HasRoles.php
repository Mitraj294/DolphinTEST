<?php

namespace App\Models\Traits;

use App\Models\Role;
use Illuminate\Support\Collection;

trait HasRoles
{
    
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users', 'user_id', 'role_id');
    }

    
    public function hasRole(string $roleName): bool
    {
        
        return $this->roles->contains('name', $roleName);
    }

    
    public function hasAnyRole(...$roles): bool
    {
        
        if (count($roles) === 1 && is_array($roles[0])) {
            $roles = $roles[0];
        }

        
        if (empty($roles)) {
            return false;
        }

        
        return $this->roles()->whereIn('name', $roles)->exists();
    }
}
