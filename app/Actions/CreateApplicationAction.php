<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\HostEnvironment;
use App\Models\Application;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

class CreateApplicationAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, User $actor): Application
    {
        $environment = $data['environment'] instanceof HostEnvironment
            ? $data['environment']
            : HostEnvironment::from((string) ($data['environment'] ?? HostEnvironment::Production->value));

        $application = Application::query()->create([
            'organization_id' => $organization->id,
            'host_id' => $data['host_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($organization->id, (string) $data['name'], $environment),
            'type' => $data['type'] instanceof ApplicationType
                ? $data['type']
                : ApplicationType::from((string) $data['type']),
            'environment' => $environment,
            'version' => $data['version'] ?? null,
            'endpoint' => $data['endpoint'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => ApplicationStatus::Unknown,
        ]);

        $this->auditLogger->log(AuditAction::ApplicationCreated, $application, newValues: [
            'name' => $application->name,
            'type' => $application->type->value,
            'environment' => $application->environment->value,
        ], organization: $organization, actor: $actor);

        return $application;
    }

    private function uniqueSlug(int $organizationId, string $name, HostEnvironment $environment): string
    {
        $base = Str::slug($name) ?: 'application';
        $slug = $base;
        $suffix = 2;

        while (Application::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('environment', $environment->value)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
