<?php

namespace App\Actions;

use App\Enums\AgentPlatform;
use App\Enums\AgentStatus;
use App\Enums\AuditAction;
use App\Enums\HostEnvironment;
use App\Enums\HostStatus;
use App\Models\Agent;
use App\Models\EnrollmentToken;
use App\Models\Host;
use App\Services\AuditLogger;
use App\Support\AgentCredentials;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAgentAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{agent: Agent, host: Host, api_key: string}
     */
    public function handle(array $data): array
    {
        $enrollment = EnrollmentToken::findUsableByPlainText($data['enrollment_token']);

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'enrollment_token' => 'The enrollment token is invalid or expired.',
            ]);
        }

        $organization = $enrollment->organization;

        if ($organization === null || $organization->isSuspended()) {
            throw ValidationException::withMessages([
                'enrollment_token' => 'This organization is suspended.',
            ]);
        }

        return DB::transaction(function () use ($data, $enrollment, $organization): array {
            $now = now();
            $apiKey = AgentCredentials::generateApiKey();
            $platform = $data['platform'] ?? AgentPlatform::Linux->value;

            $host = Host::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'hostname' => $data['hostname'],
                'display_name' => $data['display_name'] ?? $data['hostname'],
                'ip_address' => $data['ip_address'] ?? null,
                'operating_system' => $data['operating_system'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'architecture' => $data['architecture'] ?? null,
                'environment' => $data['environment'] ?? HostEnvironment::Production->value,
                'status' => HostStatus::Online,
                'last_seen_at' => $now,
                'registered_at' => $now,
            ]);

            $agent = Agent::query()->withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'host_id' => $host->id,
                'name' => $data['name'] ?? $data['hostname'],
                'agent_uid' => AgentCredentials::generateAgentUid(),
                'api_key_hash' => AgentCredentials::hash($apiKey),
                'version' => $data['version'] ?? '0.1.0',
                'platform' => $platform,
                'architecture' => $data['architecture'] ?? null,
                'status' => AgentStatus::Online,
                'last_seen_at' => $now,
                'installed_at' => $now,
            ]);

            $host->forceFill(['agent_id' => $agent->id])->save();
            $enrollment->forceFill(['last_used_at' => $now])->save();

            $this->auditLogger->log(
                AuditAction::AgentRegistered,
                $agent,
                newValues: [
                    'agent_uid' => $agent->agent_uid,
                    'hostname' => $host->hostname,
                ],
                organization: $organization,
            );

            return [
                'agent' => $agent,
                'host' => $host,
                'api_key' => $apiKey,
            ];
        });
    }
}
