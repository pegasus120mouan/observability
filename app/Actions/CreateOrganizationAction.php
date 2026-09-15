<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

class CreateOrganizationAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, User $actor): Organization
    {
        $organization = Organization::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'description' => $data['description'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'] ?? OrganizationStatus::Active,
            'timezone' => $data['timezone'] ?? 'UTC',
            'metric_retention_days' => $data['metric_retention_days'] ?? config('platform.retention.metrics_days'),
            'log_retention_days' => $data['log_retention_days'] ?? config('platform.retention.logs_days'),
            'audit_retention_days' => $data['audit_retention_days'] ?? config('platform.retention.audit_days'),
        ]);

        $this->auditLogger->log(
            AuditAction::OrganizationCreated,
            $organization,
            newValues: ['name' => $organization->name, 'slug' => $organization->slug],
            organization: $organization,
            actor: $actor,
        );

        return $organization;
    }
}
