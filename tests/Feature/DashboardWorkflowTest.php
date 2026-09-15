<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Enums\RoleName;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_creating_a_dashboard_with_starter_widgets_records_an_audit(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $response = $this->actingAsMember($admin, $organization)
            ->post(route('dashboards.store'), [
                'name' => 'Infrastructure',
                'description' => 'Starter view',
                'is_default' => '1',
                'seed_layout' => '1',
            ]);

        $dashboard = Dashboard::query()->withoutGlobalScopes()->where('name', 'Infrastructure')->first();

        $this->assertNotNull($dashboard);
        $response->assertRedirect(route('dashboards.show', $dashboard));
        $this->assertTrue($dashboard->is_default);
        $this->assertSame(8, $dashboard->widgets()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DashboardCreated->value,
            'resource_id' => $dashboard->id,
        ]);
    }

    public function test_name_is_required_to_create_a_dashboard(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->from(route('dashboards.create'))
            ->post(route('dashboards.store'), [
                'seed_layout' => '1',
            ])
            ->assertRedirect(route('dashboards.create'))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Dashboard::query()->withoutGlobalScopes()->count());
    }

    public function test_making_a_dashboard_default_clears_the_previous_default(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $first = Dashboard::factory()->forOrganization($organization)->default()->create(['name' => 'First']);
        $second = Dashboard::factory()->forOrganization($organization)->create(['name' => 'Second']);

        $this->actingAsMember($admin, $organization)
            ->put(route('dashboards.update', $second), [
                'name' => 'Second',
                'is_default' => '1',
            ])
            ->assertRedirect(route('dashboards.edit', $second));

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_show_renders_stat_and_list_widgets(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create(['name' => 'Ops board']);
        DashboardWidget::factory()->forDashboard($dashboard)->create([
            'title' => 'Open alerts',
            'type' => DashboardWidgetType::Stat,
            'config' => ['metric' => DashboardStatMetric::OpenAlerts->value],
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('dashboards.show', $dashboard))
            ->assertOk()
            ->assertSee('Ops board')
            ->assertSee('Open alerts');
    }

    public function test_adding_a_widget_persists_config(): void
    {
        $organization = Organization::factory()->create();
        $analyst = $this->createMember(RoleName::Analyst, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create();
        ['host' => $host] = $this->createMonitoredHost($organization);

        $this->actingAsMember($analyst, $organization)
            ->post(route('dashboards.widgets.store', $dashboard), [
                'title' => 'CPU chart',
                'type' => 'timeseries',
                'width' => 6,
                'metric_pair' => 'cpu.usage',
                'range' => '6h',
                'host_id' => $host->id,
            ])
            ->assertRedirect(route('dashboards.edit', $dashboard));

        $this->assertDatabaseHas('dashboard_widgets', [
            'dashboard_id' => $dashboard->id,
            'title' => 'CPU chart',
            'type' => DashboardWidgetType::Timeseries->value,
        ]);
    }

    public function test_cannot_point_a_widget_at_a_foreign_host(): void
    {
        $organization = Organization::factory()->create();
        $foreign = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create();
        ['host' => $foreignHost] = $this->createMonitoredHost($foreign);

        $this->actingAsMember($admin, $organization)
            ->from(route('dashboards.edit', $dashboard))
            ->post(route('dashboards.widgets.store', $dashboard), [
                'title' => 'Foreign CPU',
                'type' => 'timeseries',
                'width' => 6,
                'metric_pair' => 'cpu.usage',
                'host_id' => $foreignHost->id,
            ])
            ->assertRedirect(route('dashboards.edit', $dashboard))
            ->assertSessionHasErrors('host_id');

        $this->assertSame(0, DashboardWidget::query()->withoutGlobalScopes()->count());
    }

    public function test_deleting_a_dashboard_removes_its_widgets(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create();
        DashboardWidget::factory()->forDashboard($dashboard)->create();

        $this->actingAsMember($admin, $organization)
            ->delete(route('dashboards.destroy', $dashboard))
            ->assertRedirect(route('dashboards.index'));

        $this->assertSame(0, Dashboard::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, DashboardWidget::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::DashboardDeleted->value,
        ]);
    }

    public function test_dashboard_show_escapes_name(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $dashboard = Dashboard::factory()->forOrganization($organization)->create([
            'name' => "<script>alert('xss')</script>",
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('dashboards.show', $dashboard))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }
}
