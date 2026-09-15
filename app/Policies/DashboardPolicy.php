<?php

namespace App\Policies;

use App\Models\Dashboard;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class DashboardPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::DASHBOARDS_VIEW);
    }

    public function view(User $user, Dashboard $dashboard): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::DASHBOARDS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $dashboard->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::DASHBOARDS_MANAGE);
    }

    public function update(User $user, Dashboard $dashboard): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::DASHBOARDS_MANAGE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $dashboard->organization_id);
    }

    public function delete(User $user, Dashboard $dashboard): bool|Response
    {
        return $this->update($user, $dashboard);
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
