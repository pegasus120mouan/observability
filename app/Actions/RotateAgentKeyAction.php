<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Agent;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AgentCredentials;

class RotateAgentKeyAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return array{agent: Agent, api_key: string}
     */
    public function handle(Agent $agent, User $actor): array
    {
        $apiKey = AgentCredentials::generateApiKey();

        $agent->forceFill([
            'api_key_hash' => AgentCredentials::hash($apiKey),
        ])->save();

        $this->auditLogger->log(
            AuditAction::AgentKeyRotated,
            $agent,
            newValues: ['agent_uid' => $agent->agent_uid],
            actor: $actor,
        );

        return ['agent' => $agent, 'api_key' => $apiKey];
    }
}
