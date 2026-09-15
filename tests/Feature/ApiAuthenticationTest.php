<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_returns_401_when_no_token_is_provided(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_login_returns_a_token_and_standard_payload(): void
    {
        $user = $this->createMember(RoleName::Admin);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonStructure([
                'success',
                'data' => ['token', 'token_type', 'user'],
                'message',
                'meta',
            ]);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->createMember(RoleName::Analyst, $organization);

        Sanctum::actingAs($user);

        $this->withHeaders(['X-Organization-Id' => $organization->id])
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.role', RoleName::Analyst->value);
    }

    public function test_validation_errors_use_the_standard_error_envelope(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors']);
    }

    public function test_api_user_show_returns_404_for_another_organization(): void
    {
        $organizationA = Organization::factory()->create();
        $organizationB = Organization::factory()->create();
        $adminA = $this->createMember(RoleName::Admin, $organizationA);
        $adminB = $this->createMember(RoleName::Admin, $organizationB);

        Sanctum::actingAs($adminA);

        $this->withHeaders(['X-Organization-Id' => $organizationA->id])
            ->getJson('/api/v1/users/'.$adminB->id)
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_super_admin_can_list_organizations_through_the_api(): void
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create([
            'current_organization_id' => $organization->id,
        ]);

        Sanctum::actingAs($superAdmin);

        $this->withHeaders(['X-Organization-Id' => $organization->id])
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
