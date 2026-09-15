<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\EnrollmentToken;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\AgentCredentials;

class CreateEnrollmentTokenAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @return array{token: EnrollmentToken, plain_text: string}
     */
    public function handle(Organization $organization, string $name, User $actor, ?int $expiresInDays = 30): array
    {
        $plainText = AgentCredentials::generateEnrollmentToken();

        $token = EnrollmentToken::query()->create([
            'organization_id' => $organization->id,
            'created_by' => $actor->id,
            'name' => $name,
            'token_hash' => AgentCredentials::hash($plainText),
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
        ]);

        $this->auditLogger->log(
            AuditAction::EnrollmentTokenCreated,
            $token,
            newValues: ['name' => $token->name],
            organization: $organization,
            actor: $actor,
        );

        return ['token' => $token, 'plain_text' => $plainText];
    }
}
