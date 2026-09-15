<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_super_admin_can_create_an_organization(): void
    {
        $organization = Organization::factory()->create();
        $superAdmin = User::factory()->superAdmin()->create([
            'current_organization_id' => $organization->id,
        ]);

        $this->actingAsMember($superAdmin, $organization)
            ->post(route('organizations.store'), [
                'name' => 'Northwind',
                'slug' => 'northwind',
                'status' => 'trial',
                'timezone' => 'UTC',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('organizations', [
            'name' => 'Northwind',
            'slug' => 'northwind',
            'status' => 'trial',
        ]);
    }

    public function test_admin_can_update_current_organization_settings(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->put(route('settings.organization.update'), [
                'name' => 'Acme Updated',
                'slug' => $organization->slug,
                'status' => 'active',
                'timezone' => 'Europe/Paris',
                'metric_retention_days' => 45,
                'log_retention_days' => 120,
                'audit_retention_days' => 400,
            ])
            ->assertRedirect();

        $organization->refresh();

        $this->assertSame('Acme Updated', $organization->name);
        $this->assertSame(45, $organization->metric_retention_days);
        $this->assertSame('Europe/Paris', $organization->timezone);
    }
}
