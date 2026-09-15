<?php

namespace App\Policies;

use App\Models\Host;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class HostPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::HOSTS_VIEW);
    }

    public function view(User $user, Host $host): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::HOSTS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $host->organization_id);
    }

    public function update(User $user, Host $host): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::HOSTS_UPDATE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $host->organization_id);
    }

    private function authorizeCurrentOrganization(User $user, int $organizationId): bool|Response
    {
        $organization = $this->tenantContext->organization();

        if ($user->isSuperAdmin() && ($organization === null || $organization->id === $organizationId)) {
            return true;
        }

        if ($organization !== null && $organization->id === $organizationId) {
            return true;
        }

        return Response::denyAsNotFound();
    }
}
