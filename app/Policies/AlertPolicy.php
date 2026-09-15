<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class AlertPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::ALERTS_VIEW);
    }

    public function view(User $user, Alert $alert): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::ALERTS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $alert->organization_id);
    }

    public function update(User $user, Alert $alert): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::ALERTS_UPDATE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $alert->organization_id);
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
