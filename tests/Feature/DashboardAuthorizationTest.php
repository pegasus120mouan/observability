<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Dashboard;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_viewer_can_open_dashboards_but_cannot_create_one(): void
    {
        $organization = Organization::factory()->create();
        $viewer = $this->createMember(RoleName::Viewer, $organization);
        Dashboard::factory()->forOrganization($organization)->create(['name' => 'visible dashboard']);

        $this->actingAsMember($viewer, $organization)
            ->get(route('dashboards.index'))
            ->assertOk()
            ->assertSee('visible dashboard');

        $this->actingAsMember($viewer, $organization)
            ->get(route('dashboards.create'))
            ->assertForbidden();

        $this->actingAsMember($viewer, $organization)
            ->post(route('dashboards.store'), [
                'name' => 'Forbidden dashboard',
            ])
            ->assertForbidden();
    }

    public function test_operator_cannot_edit_a_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $operator = $this->createMember(RoleName::Operator, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create();

        $this->actingAsMember($operator, $organization)
            ->get(route('dashboards.show', $dashboard))
            ->assertOk();

        $this->actingAsMember($operator, $organization)
            ->get(route('dashboards.edit', $dashboard))
            ->assertForbidden();

        $this->actingAsMember($operator, $organization)
            ->put(route('dashboards.update', $dashboard), [
                'name' => 'Should not save',
            ])
            ->assertForbidden();
    }

    public function test_analyst_can_create_a_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $analyst = $this->createMember(RoleName::Analyst, $organization);

        $response = $this->actingAsMember($analyst, $organization)
            ->post(route('dashboards.store'), [
                'name' => 'Analyst board',
            ]);

        $dashboard = Dashboard::query()->withoutGlobalScopes()->where('name', 'Analyst board')->first();

        $this->assertNotNull($dashboard);
        $response->assertRedirect(route('dashboards.show', $dashboard));
    }

    public function test_guest_is_redirected_from_dashboards(): void
    {
        $this->get(route('dashboards.index'))->assertRedirect(route('login'));
    }
}
