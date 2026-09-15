<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;

class UpdateOrganizationAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, User $actor): Organization
    {
        $oldValues = $organization->only([
            'name',
            'description',
            'email',
            'phone',
            'address',
            'status',
            'timezone',
            'metric_retention_days',
            'log_retention_days',
            'audit_retention_days',
        ]);

        $organization->update($data);

        $this->auditLogger->log(
            AuditAction::OrganizationUpdated,
            $organization,
            oldValues: $oldValues,
            newValues: $organization->only(array_keys($oldValues)),
            organization: $organization,
            actor: $actor,
        );

        return $organization;
    }
}
