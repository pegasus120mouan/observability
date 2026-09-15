<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class ApplicationPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::APPLICATIONS_VIEW);
    }

    public function view(User $user, Application $application): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::APPLICATIONS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $application->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::APPLICATIONS_MANAGE);
    }

    public function update(User $user, Application $application): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::APPLICATIONS_MANAGE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $application->organization_id);
    }

    public function delete(User $user, Application $application): bool|Response
    {
        return $this->update($user, $application);
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
