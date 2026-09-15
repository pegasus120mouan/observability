<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\ApplicationRequest;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_creating_an_application_records_an_audit(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $response = $this->actingAsMember($admin, $organization)
            ->post(route('applications.store'), [
                'name' => 'orders-api',
                'type' => 'laravel',
                'environment' => 'production',
                'endpoint' => 'https://api.acme.test/orders',
            ]);

        $application = Application::query()->withoutGlobalScopes()->where('name', 'orders-api')->first();

        $this->assertNotNull($application);
        $response->assertRedirect(route('applications.show', $application));
        $this->assertSame('orders-api', $application->slug);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationCreated->value,
            'resource_id' => $application->id,
        ]);
    }

    public function test_name_is_required_to_create_an_application(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);

        $this->actingAsMember($admin, $organization)
            ->from(route('applications.create'))
            ->post(route('applications.store'), [
                'type' => 'laravel',
                'environment' => 'production',
            ])
            ->assertRedirect(route('applications.create'))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
    }

    public function test_show_renders_request_and_error_stats(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create(['name' => 'orders-api']);
        ApplicationMetric::factory()->forApplication($application)->create([
            'request_count' => 12542,
            'error_count' => 100,
            'response_time_avg' => 182,
            'response_time_p95' => 421,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('orders-api')
            ->assertSee('12,542')
            ->assertSee('182 ms')
            ->assertSee('421 ms')
            ->assertSee('Requests')
            ->assertSee('Errors')
            ->assertSee('Recent requests')
            ->assertSee('metric-chart')
            ->assertSee('Latency')
            ->assertSee('"type":"bar"', false)
            ->assertSee('"unit":"count"', false)
            ->assertSee('"unit":"ms"', false);
    }

    public function test_show_renders_recent_http_requests_and_live_payload(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => 'Apache',
            'type' => ApplicationType::Apache,
            'runtime_stats' => [
                'req_per_sec' => 4.2,
                'busy_workers' => 2,
                'idle_workers' => 8,
            ],
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(5),
            'method' => 'GET',
            'resource' => '/index.php',
            'status_code' => 200,
            'duration_us' => 2740,
            'geo_country' => 'FR',
            'geo_lat' => 46.2276,
            'geo_lng' => 2.2137,
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(3),
            'method' => 'POST',
            'resource' => '/login',
            'status_code' => 500,
            'duration_us' => 18400,
            'geo_country' => 'US',
            'geo_lat' => 37.751,
            'geo_lng' => -97.822,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('data-live-application', false)
            ->assertSee('/index.php')
            ->assertSee('/login')
            ->assertSee('GET')
            ->assertSee('POST')
            ->assertSee('2.74 ms')
            ->assertSee('p50 / p75 / p90 / p95 / p99 / Max')
            ->assertSee('Requests / sec')
            ->assertSee('Workers')
            ->assertSee('5xx / requests')
            ->assertSee('Client map')
            ->assertSee('Top locations')
            ->assertSee('France')
            ->assertSee('United States')
            ->assertSee('4.20');

        $this->actingAsMember($admin, $organization)
            ->getJson(route('applications.live', ['application' => $application, 'range' => '15m']))
            ->assertOk()
            ->assertJsonPath('summary.request_count', 2)
            ->assertJsonPath('summary.error_count', 1)
            ->assertJsonPath('summary.client_error_count', 0)
            ->assertJsonPath('summary.has_duration', true)
            ->assertJsonPath('charts.requests.type', 'bar')
            ->assertJsonPath('charts.requests.histogram', true)
            ->assertJsonPath('charts.latency.series.0.label', 'p50')
            ->assertJsonPath('recent.0.resource', '/login')
            ->assertJsonPath('recent.0.status_code', 500)
            ->assertJsonPath('health.status', ApplicationStatus::Critical->value)
            ->assertJsonPath('status', ApplicationStatus::Critical->value)
            ->assertJsonPath('runtime.req_per_sec', 4.2)
            ->assertJsonPath('runtime.busy_workers', 2)
            ->assertJsonPath('usage_map.client_locations', 2)
            ->assertJsonPath('recent.0.location_label', 'United States')
            ->assertJsonMissingPath('recent.0.client_ip');
    }

    public function test_client_errors_do_not_mark_the_application_unhealthy(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => 'Apache',
            'type' => ApplicationType::Apache,
            'status' => ApplicationStatus::Critical,
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(4),
            'method' => 'GET',
            'resource' => '/',
            'status_code' => 200,
            'duration_us' => 1200,
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(2),
            'method' => 'GET',
            'resource' => '/missing',
            'status_code' => 404,
            'duration_us' => 800,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('0 5xx')
            ->assertSee('1 4xx')
            ->assertSee('Healthy')
            ->assertSee('5xx / requests');

        $this->actingAsMember($admin, $organization)
            ->getJson(route('applications.live', ['application' => $application, 'range' => '15m']))
            ->assertOk()
            ->assertJsonPath('summary.request_count', 2)
            ->assertJsonPath('summary.error_count', 0)
            ->assertJsonPath('summary.client_error_count', 1)
            ->assertJsonPath('summary.error_rate', 0)
            ->assertJsonPath('health.status', ApplicationStatus::Healthy->value)
            ->assertJsonPath('status', ApplicationStatus::Healthy->value);
    }

    public function test_show_hides_latency_when_duration_is_not_logged(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => 'Apache',
            'type' => ApplicationType::Apache,
        ]);
        ApplicationRequest::factory()->forApplication($application)->create([
            'occurred_at' => now()->subSeconds(3),
            'method' => 'GET',
            'resource' => '/',
            'status_code' => 200,
            'duration_us' => 0,
        ]);

        $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->assertOk()
            ->assertSee('No duration in access log')
            ->assertSee('Duration is not in the access log');

        $this->actingAsMember($admin, $organization)
            ->getJson(route('applications.live', ['application' => $application, 'range' => '15m']))
            ->assertOk()
            ->assertJsonPath('summary.has_duration', false)
            ->assertJsonPath('charts.latency.empty', 'Duration is not in the access log. Add %D (Apache) or $request_time (Nginx).');
    }

    public function test_application_show_escapes_http_resource(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create(['name' => 'Apache']);
        ApplicationRequest::factory()->forApplication($application)->create([
            'resource' => "<script>alert('xss')</script>",
            'method' => 'GET',
            'status_code' => 200,
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }

    public function test_deleting_an_application_removes_its_metrics(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create();
        ApplicationMetric::factory()->forApplication($application)->create();
        ApplicationRequest::factory()->forApplication($application)->create();

        $this->actingAsMember($admin, $organization)
            ->delete(route('applications.destroy', $application))
            ->assertRedirect(route('applications.index'));

        $this->assertSame(0, Application::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationMetric::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, ApplicationRequest::query()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationDeleted->value,
        ]);
    }

    public function test_application_show_escapes_name(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->createMember(RoleName::Admin, $organization);
        $application = Application::factory()->forOrganization($organization)->create([
            'name' => "<script>alert('xss')</script>",
        ]);

        $content = $this->actingAsMember($admin, $organization)
            ->get(route('applications.show', $application))
            ->getContent();

        $this->assertStringContainsString('&lt;script&gt;', $content);
        $this->assertStringNotContainsString("<script>alert('xss')</script>", $content);
    }
}
