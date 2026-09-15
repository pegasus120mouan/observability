<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class IncidentPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::INCIDENTS_VIEW);
    }

    public function view(User $user, Incident $incident): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::INCIDENTS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $incident->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::INCIDENTS_UPDATE);
    }

    public function update(User $user, Incident $incident): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::INCIDENTS_UPDATE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $incident->organization_id);
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
