<?php

namespace App\Policies;

use App\Models\Agent;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Illuminate\Auth\Access\Response;

class AgentPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::AGENTS_VIEW);
    }

    public function view(User $user, Agent $agent): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::AGENTS_VIEW)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($agent->organization_id, $user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::AGENTS_MANAGE);
    }

    public function update(User $user, Agent $agent): bool|Response
    {
        if (! $user->hasPermission(PermissionCatalog::AGENTS_MANAGE)) {
            return false;
        }

        return $this->authorizeCurrentOrganization($agent->organization_id, $user);
    }

    private function authorizeCurrentOrganization(int $organizationId, User $user): bool|Response
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
