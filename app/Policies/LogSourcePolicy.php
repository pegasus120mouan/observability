<?php

namespace App\Policies;

use App\Models\LogSource;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class LogSourcePolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::LOGS_VIEW);
    }

    public function view(User $user, LogSource $logSource): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::LOGS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $logSource->organization_id);
    }

    public function update(User $user, LogSource $logSource): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::LOGS_MANAGE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $logSource->organization_id);
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
