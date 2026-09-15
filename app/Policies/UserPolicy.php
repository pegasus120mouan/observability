<?php

namespace App\Policies;

use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::USERS_VIEW);
    }

    public function view(User $actor, User $user): bool|Response
    {
        if (! $actor->hasPermission(PermissionCatalog::USERS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($actor, $user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::USERS_CREATE);
    }

    public function update(User $actor, User $user): bool|Response
    {
        if (! $actor->hasPermission(PermissionCatalog::USERS_UPDATE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($actor, $user);
    }

    public function delete(User $actor, User $user): bool|Response
    {
        if ($actor->is($user)) {
            return false;
        }

        if (! $actor->hasPermission(PermissionCatalog::USERS_DELETE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($actor, $user);
    }

    private function authorizeCurrentOrganization(User $actor, User $user): bool|Response
    {
        if ($this->sharesCurrentOrganization($actor, $user)) {
            return true;
        }

        return Response::denyAsNotFound();
    }

    private function sharesCurrentOrganization(User $actor, User $user): bool
    {
        $organization = $this->tenantContext->organization();

        if ($organization === null) {
            return $actor->isSuperAdmin();
        }

        return $user->belongsToOrganization($organization);
    }
}
