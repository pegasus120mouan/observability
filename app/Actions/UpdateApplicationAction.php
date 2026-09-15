<?php

namespace App\Actions;

use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\HostEnvironment;
use App\Models\Application;
use App\Services\AuditLogger;

class UpdateApplicationAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Application $application, array $data): Application
    {
        $previous = $application->only(['name', 'type', 'environment', 'version', 'endpoint', 'host_id']);

        $application->fill([
            'name' => $data['name'] ?? $application->name,
            'type' => $data['type'] instanceof ApplicationType
                ? $data['type']
                : ($data['type'] ?? $application->type),
            'environment' => $data['environment'] instanceof HostEnvironment
                ? $data['environment']
                : ($data['environment'] ?? $application->environment),
            'version' => array_key_exists('version', $data) ? $data['version'] : $application->version,
            'endpoint' => array_key_exists('endpoint', $data) ? $data['endpoint'] : $application->endpoint,
            'description' => array_key_exists('description', $data) ? $data['description'] : $application->description,
            'host_id' => array_key_exists('host_id', $data) ? $data['host_id'] : $application->host_id,
        ])->save();

        $this->auditLogger->log(
            AuditAction::ApplicationUpdated,
            $application,
            oldValues: $previous,
            newValues: $application->only(['name', 'type', 'environment', 'version', 'endpoint', 'host_id']),
        );

        return $application->fresh() ?? $application;
    }
}
