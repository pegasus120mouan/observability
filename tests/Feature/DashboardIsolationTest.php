<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_dashboard_list_does_not_include_another_organization(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);

        Dashboard::factory()->forOrganization($acme)->create(['name' => 'acme unique dashboard']);
        Dashboard::factory()->forOrganization($globex)->create(['name' => 'globex unique dashboard']);

        $this->actingAsMember($admin, $acme)
            ->get(route('dashboards.index'))
            ->assertOk()
            ->assertSee('acme unique dashboard')
            ->assertDontSee('globex unique dashboard');
    }

    public function test_cross_tenant_dashboard_show_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreign = Dashboard::factory()->forOrganization($globex)->create(['name' => 'secret dashboard']);

        $this->actingAsMember($admin, $acme)
            ->get(route('dashboards.show', $foreign))
            ->assertNotFound();
    }

    public function test_cross_tenant_dashboard_update_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $foreign = Dashboard::factory()->forOrganization($globex)->create(['name' => 'globex board']);

        $this->actingAsMember($admin, $acme)
            ->put(route('dashboards.update', $foreign), [
                'name' => 'hijacked',
            ])
            ->assertNotFound();

        $this->assertSame(
            'globex board',
            Dashboard::query()->withoutGlobalScopes()->find($foreign->id)?->name,
        );
    }

    public function test_cross_tenant_widget_delete_returns_404(): void
    {
        $acme = Organization::factory()->create();
        $globex = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $acme);
        $local = Dashboard::factory()->forOrganization($acme)->create();
        $foreign = Dashboard::factory()->forOrganization($globex)->create();
        $widget = DashboardWidget::factory()->forDashboard($foreign)->create();

        $this->actingAsMember($admin, $acme)
            ->delete(route('dashboards.widgets.destroy', [$local, $widget]))
            ->assertNotFound();

        $this->assertNotNull(DashboardWidget::query()->withoutGlobalScopes()->find($widget->id));
    }
}
