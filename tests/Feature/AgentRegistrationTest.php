<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\Agent;
use App\Models\EnrollmentToken;
use App\Models\Organization;
use Database\Factories\EnrollmentTokenFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_agent_registers_with_a_valid_enrollment_token(): void
    {
        $organization = Organization::factory()->create();
        $plainToken = EnrollmentTokenFactory::TEST_TOKEN;

        EnrollmentToken::factory()->forPlainText($plainToken)->create([
            'organization_id' => $organization->id,
            'expires_at' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/v1/agent/register', [
            'enrollment_token' => $plainToken,
            'hostname' => 'web-01.acme.test',
            'ip_address' => '10.0.1.11',
            'operating_system' => 'Ubuntu',
            'os_version' => '24.04',
            'architecture' => 'x86_64',
            'platform' => 'linux',
            'version' => '0.1.0',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hostname', 'web-01.acme.test')
            ->assertJsonStructure([
                'data' => ['agent_id', 'api_key', 'host_id', 'hostname', 'config'],
            ]);

        $this->assertStringStartsWith('saha_', $response->json('data.api_key'));
        $this->assertStringStartsWith('AGT-', $response->json('data.agent_id'));

        $this->assertDatabaseHas('hosts', [
            'organization_id' => $organization->id,
            'hostname' => 'web-01.acme.test',
        ]);
        $this->assertDatabaseHas('agents', [
            'organization_id' => $organization->id,
            'name' => 'web-01.acme.test',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AgentRegistered->value,
            'organization_id' => $organization->id,
        ]);
    }

    public function test_registration_returns_the_api_key_only_in_the_response(): void
    {
        $organization = Organization::factory()->create();
        $plainToken = 'enroll_once_visible_token';

        EnrollmentToken::factory()->forPlainText($plainToken)->create([
            'organization_id' => $organization->id,
        ]);

        $apiKey = $this->postJson('/api/v1/agent/register', [
            'enrollment_token' => $plainToken,
            'hostname' => 'once.acme.test',
        ])->json('data.api_key');

        $this->assertNotEmpty($apiKey);
        $this->assertDatabaseMissing('agents', ['api_key_hash' => $apiKey]);
        $this->assertTrue(
            Agent::query()->withoutGlobalScopes()->firstOrFail()->apiKeyMatches($apiKey)
        );
        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['api_key' => $apiKey])]);
    }

    public function test_invalid_enrollment_token_is_rejected(): void
    {
        $this->postJson('/api/v1/agent/register', [
            'enrollment_token' => 'enroll_does_not_exist',
            'hostname' => 'ghost.acme.test',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);
    }

    public function test_revoked_enrollment_token_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $plainToken = 'enroll_revoked_token';

        EnrollmentToken::factory()->forPlainText($plainToken)->revoked()->create([
            'organization_id' => $organization->id,
        ]);

        $this->postJson('/api/v1/agent/register', [
            'enrollment_token' => $plainToken,
            'hostname' => 'revoked.acme.test',
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('hosts', ['hostname' => 'revoked.acme.test']);
    }
}
