<?php

namespace App\Policies;

use App\Models\AlertRule;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class AlertRulePolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::ALERTS_VIEW);
    }

    public function view(User $user, AlertRule $alertRule): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::ALERTS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $alertRule->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::ALERTS_MANAGE);
    }

    public function update(User $user, AlertRule $alertRule): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::ALERTS_MANAGE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($user, $alertRule->organization_id);
    }

    public function delete(User $user, AlertRule $alertRule): bool|Response
    {
        return $this->update($user, $alertRule);
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
