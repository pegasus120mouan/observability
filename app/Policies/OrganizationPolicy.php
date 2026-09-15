<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Support\PermissionCatalog;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin() || $user->belongsToOrganization($organization);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin()
            || ($user->belongsToOrganization($organization)
                && $user->hasPermission(PermissionCatalog::ORGANIZATIONS_UPDATE, $organization));
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $user->isSuperAdmin();
    }
}
