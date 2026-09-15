<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;

class AuditLogPolicy
{
    public function __construct(private TenantContext $tenantContext) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(PermissionCatalog::AUDIT_VIEW);
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        if (! $user->hasPermission(PermissionCatalog::AUDIT_VIEW)) {
            return false;
        }

        $organization = $this->tenantContext->organization();

        if ($organization === null) {
            return $user->isSuperAdmin();
        }

        return $auditLog->organization_id === $organization->id;
    }
}
