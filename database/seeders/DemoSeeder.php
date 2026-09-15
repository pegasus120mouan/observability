<?php

namespace Database\Seeders;

use App\Actions\CreateDashboardAction;
use App\Actions\CreateIncidentAction;
use App\Actions\EvaluateAlertsAction;
use App\Actions\RecordIncidentEventAction;
use App\Actions\UpdateIncidentAction;
use App\Enums\AgentPlatform;
use App\Enums\AgentStatus;
use App\Enums\AlertCondition;
use App\Enums\AlertMetric;
use App\Enums\AlertSeverity;
use App\Enums\ApplicationType;
use App\Enums\HostEnvironment;
use App\Enums\HostStatus;
use App\Enums\IncidentEventType;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\LogLevel;
use App\Enums\LogSourceStatus;
use App\Enums\LogSourceType;
use App\Enums\MembershipStatus;
use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Agent;
use App\Models\Alert;
use App\Models\AlertRule;
use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\Dashboard;
use App\Models\Host;
use App\Models\Incident;
use App\Models\LogEntry;
use App\Models\LogSource;
use App\Models\MetricSample;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\AgentCredentials;
use App\Support\ApmCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'acme-corp'],
            [
                'name' => 'Acme Corporation',
                'description' => 'Demo organization used to explore SAHA Observability.',
                'email' => 'it@acme.test',
                'phone' => '+33 1 23 45 67 89',
                'address' => '42 Rue de la Supervision, Paris',
                'status' => OrganizationStatus::Active,
                'timezone' => 'Europe/Paris',
                'metric_retention_days' => 30,
                'log_retention_days' => 90,
                'audit_retention_days' => 365,
            ],
        );

        $roles = Role::query()->whereIn('name', RoleName::cases())->get()->keyBy(fn (Role $role) => $role->name->value);

        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@saha.test'],
            [
                'name' => 'Saha Super Admin',
                'password' => Hash::make('password'),
                'is_super_admin' => true,
                'status' => UserStatus::Active,
                'current_organization_id' => $organization->id,
            ],
        );

        $this->attach($organization, $superAdmin, $roles[RoleName::Admin->value]);

        $users = [
            ['email' => 'admin@acme.test', 'name' => 'Alice Admin', 'role' => RoleName::Admin],
            ['email' => 'analyst@acme.test', 'name' => 'Alex Analyst', 'role' => RoleName::Analyst],
            ['email' => 'operator@acme.test', 'name' => 'Omar Operator', 'role' => RoleName::Operator],
            ['email' => 'viewer@acme.test', 'name' => 'Vera Viewer', 'role' => RoleName::Viewer],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'is_super_admin' => false,
                    'status' => UserStatus::Active,
                    'current_organization_id' => $organization->id,
                ],
            );

            $this->attach($organization, $user, $roles[$userData['role']->value]);
        }

        $this->seedHosts($organization);
        $this->seedMetrics($organization);
        $this->seedLogs($organization);
        $this->seedAlertRules($organization);
        $this->seedIncidents($organization);
        $this->seedDashboards($organization);
        $this->seedApplications($organization);
    }

    private function attach(Organization $organization, User $user, Role $role): void
    {
        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role_id' => $role->id,
                'status' => MembershipStatus::Active->value,
            ],
        ]);
    }

    private function seedHosts(Organization $organization): void
    {
        $hosts = [
            ['hostname' => 'web-01.acme.test', 'ip' => '10.0.1.11', 'os' => 'Ubuntu', 'version' => '24.04', 'env' => HostEnvironment::Production, 'status' => HostStatus::Online],
            ['hostname' => 'web-02.acme.test', 'ip' => '10.0.1.12', 'os' => 'Ubuntu', 'version' => '24.04', 'env' => HostEnvironment::Production, 'status' => HostStatus::Online],
            ['hostname' => 'db-01.acme.test', 'ip' => '10.0.2.21', 'os' => 'Debian', 'version' => '12', 'env' => HostEnvironment::Production, 'status' => HostStatus::Warning],
            ['hostname' => 'stg-api-01.acme.test', 'ip' => '10.0.8.31', 'os' => 'Ubuntu', 'version' => '24.04', 'env' => HostEnvironment::Staging, 'status' => HostStatus::Online],
            ['hostname' => 'legacy-01.acme.test', 'ip' => '10.0.9.41', 'os' => 'Windows Server', 'version' => '2022', 'env' => HostEnvironment::Development, 'status' => HostStatus::Offline],
        ];

        foreach ($hosts as $index => $data) {
            $lastSeen = $data['status'] === HostStatus::Offline
                ? now()->subMinutes(30)
                : now()->subMinutes($index);

            $host = Host::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'hostname' => $data['hostname'],
                ],
                [
                    'display_name' => strtoupper(str_replace('.acme.test', '', $data['hostname'])),
                    'ip_address' => $data['ip'],
                    'operating_system' => $data['os'],
                    'os_version' => $data['version'],
                    'architecture' => 'x86_64',
                    'environment' => $data['env'],
                    'status' => $data['status'],
                    'last_seen_at' => $lastSeen,
                    'registered_at' => now()->subDays(7),
                ],
            );

            $existingAgent = Agent::query()
                ->where('organization_id', $organization->id)
                ->where('name', $data['hostname'])
                ->first();

            $agent = Agent::query()->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'name' => $data['hostname'],
                ],
                [
                    'host_id' => $host->id,
                    'agent_uid' => $existingAgent?->agent_uid ?? AgentCredentials::generateAgentUid(),
                    'api_key_hash' => $existingAgent?->api_key_hash ?? AgentCredentials::hash(AgentCredentials::generateApiKey()),
                    'version' => '0.1.0',
                    'platform' => str_contains($data['os'], 'Windows') ? AgentPlatform::Windows : AgentPlatform::Linux,
                    'architecture' => 'x86_64',
                    'status' => $data['status'] === HostStatus::Offline ? AgentStatus::Offline : AgentStatus::Online,
                    'last_seen_at' => $lastSeen,
                    'installed_at' => now()->subDays(7),
                ],
            );

            $host->forceFill(['agent_id' => $agent->id])->save();
        }
    }

    private function seedMetrics(Organization $organization): void
    {
        MetricSample::query()->where('organization_id', $organization->id)->delete();
        $profiles = [
            'web-01.acme.test' => ['cpu' => 28.0, 'memory' => 44.0, 'disk' => 38.0],
            'web-02.acme.test' => ['cpu' => 33.0, 'memory' => 51.0, 'disk' => 41.0],
            'db-01.acme.test' => ['cpu' => 61.0, 'memory' => 72.0, 'disk' => 84.0],
            'stg-api-01.acme.test' => ['cpu' => 18.0, 'memory' => 36.0, 'disk' => 29.0],
        ];

        $hosts = Host::query()
            ->where('organization_id', $organization->id)
            ->whereIn('hostname', array_keys($profiles))
            ->get();

        $rows = [];
        $now = now();

        foreach ($hosts as $host) {
            $profile = $profiles[$host->hostname];

            for ($offset = 72; $offset >= 0; $offset--) {
                $collectedAt = $now->copy()->subMinutes($offset * 5);
                $wave = sin($offset / 6) * 5;
                $createdAt = $collectedAt->copy();

                $cpu = max(1, min(99, $profile['cpu'] + $wave));
                $memory = max(1, min(99, $profile['memory'] + ($wave / 2)));
                $disk = max(1, min(99, $profile['disk'] + ($wave / 8)));
                $rx = 1_200_000 + ((72 - $offset) * 18_000);
                $tx = 800_000 + ((72 - $offset) * 11_000);

                $rows[] = $this->sampleRow($organization->id, $host->id, 'cpu', 'usage', $cpu, 'percent', $collectedAt, $createdAt);
                $rows[] = $this->sampleRow($organization->id, $host->id, 'memory', 'usage', $memory, 'percent', $collectedAt, $createdAt);
                $rows[] = $this->sampleRow($organization->id, $host->id, 'disk', 'usage', $disk, 'percent', $collectedAt, $createdAt);
                $rows[] = $this->sampleRow($organization->id, $host->id, 'network', 'rx_bytes', $rx, 'bytes', $collectedAt, $createdAt);
                $rows[] = $this->sampleRow($organization->id, $host->id, 'network', 'tx_bytes', $tx, 'bytes', $collectedAt, $createdAt);
                $rows[] = $this->sampleRow($organization->id, $host->id, 'load', 'load1', max(0.1, $cpu / 25), 'load', $collectedAt, $createdAt);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            MetricSample::query()->insert($chunk);
        }
    }

    private function seedLogs(Organization $organization): void
    {
        LogEntry::query()->where('organization_id', $organization->id)->delete();
        LogSource::query()->where('organization_id', $organization->id)->delete();

        $catalog = [
            'web-01.acme.test' => [
                ['name' => 'apache', 'process' => 'apache2', 'messages' => [
                    [LogLevel::Info, 'GET /health 200 12ms'],
                    [LogLevel::Warning, 'AH00128: File does not exist: /var/www/favicon.ico'],
                    [LogLevel::Error, 'AH00094: Command line: apache2 -D FOREGROUND'],
                ]],
                ['name' => 'sshd', 'process' => 'sshd', 'messages' => [
                    [LogLevel::Info, 'Accepted publickey for deploy from 10.0.1.40'],
                    [LogLevel::Warning, 'Failed password for invalid user admin from 203.0.113.10'],
                ]],
            ],
            'web-02.acme.test' => [
                ['name' => 'nginx', 'process' => 'nginx', 'messages' => [
                    [LogLevel::Info, 'GET /api/status 200'],
                    [LogLevel::Error, 'connect() failed (111: Connection refused) while connecting to upstream'],
                ]],
            ],
            'db-01.acme.test' => [
                ['name' => 'mysql', 'process' => 'mysqld', 'messages' => [
                    [LogLevel::Warning, 'Aborted connection 1842 to db: acme user: app host: web-01'],
                    [LogLevel::Error, 'InnoDB: Disk is almost full'],
                    [LogLevel::Critical, 'Server shutdown initiated'],
                ]],
            ],
            'stg-api-01.acme.test' => [
                ['name' => 'php-fpm', 'process' => 'php-fpm', 'messages' => [
                    [LogLevel::Info, 'pool www: child 221 started'],
                    [LogLevel::Notice, 'slow request /checkout 1.8s'],
                ]],
            ],
        ];

        $hosts = Host::query()
            ->where('organization_id', $organization->id)
            ->whereIn('hostname', array_keys($catalog))
            ->get()
            ->keyBy('hostname');

        $now = now();
        $rows = [];

        foreach ($catalog as $hostname => $sources) {
            $host = $hosts->get($hostname);

            if ($host === null) {
                continue;
            }

            foreach ($sources as $sourceData) {
                $source = LogSource::query()->create([
                    'organization_id' => $organization->id,
                    'host_id' => $host->id,
                    'name' => $sourceData['name'],
                    'type' => LogSourceType::Agent,
                    'status' => LogSourceStatus::Active,
                    'configuration' => [],
                ]);

                foreach ($sourceData['messages'] as $index => [$level, $message]) {
                    for ($copy = 0; $copy < 8; $copy++) {
                        $rows[] = [
                            'organization_id' => $organization->id,
                            'host_id' => $host->id,
                            'source_id' => $source->id,
                            'logged_at' => $now->copy()->subMinutes(($index + 1) * 7 + $copy * 11),
                            'level' => $level->value,
                            'message' => $message,
                            'source' => $sourceData['name'],
                            'facility' => null,
                            'event_id' => null,
                            'ip_address' => $copy % 2 === 0 ? '10.0.1.40' : '203.0.113.10',
                            'username' => $sourceData['name'] === 'sshd' ? 'deploy' : 'app',
                            'process' => $sourceData['process'],
                            'metadata' => json_encode(['seed' => true]),
                            'created_at' => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            LogEntry::query()->insert($chunk);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleRow(int $organizationId, int $hostId, string $type, string $name, float $value, string $unit, Carbon $collectedAt, Carbon $createdAt): array
    {
        return [
            'organization_id' => $organizationId,
            'host_id' => $hostId,
            'metric_type' => $type,
            'metric_name' => $name,
            'value' => round($value, 4),
            'unit' => $unit,
            'collected_at' => $collectedAt,
            'metadata' => null,
            'created_at' => $createdAt,
        ];
    }

    private function seedAlertRules(Organization $organization): void
    {
        Alert::query()->where('organization_id', $organization->id)->delete();
        AlertRule::query()->where('organization_id', $organization->id)->delete();

        $rules = [
            [
                'name' => 'CPU critical',
                'description' => 'CPU usage above 90% for five minutes.',
                'metric_type' => AlertMetric::Cpu,
                'threshold' => 90,
                'duration' => 5,
                'severity' => AlertSeverity::Critical,
            ],
            [
                'name' => 'Memory high',
                'description' => 'Memory usage above 90% for ten minutes.',
                'metric_type' => AlertMetric::Memory,
                'threshold' => 90,
                'duration' => 10,
                'severity' => AlertSeverity::High,
            ],
            [
                'name' => 'Disk high',
                'description' => 'Disk usage above 80% for five minutes.',
                'metric_type' => AlertMetric::Disk,
                'threshold' => 80,
                'duration' => 5,
                'severity' => AlertSeverity::High,
            ],
            [
                'name' => 'Host offline',
                'description' => 'Host missed heartbeats for five minutes.',
                'metric_type' => AlertMetric::HostOffline,
                'threshold' => null,
                'duration' => 5,
                'severity' => AlertSeverity::Critical,
            ],
            [
                'name' => 'Error logs bursting',
                'description' => 'More than three error-level logs in fifteen minutes.',
                'metric_type' => AlertMetric::LogError,
                'threshold' => 3,
                'duration' => 15,
                'severity' => AlertSeverity::Medium,
            ],
        ];

        foreach ($rules as $rule) {
            AlertRule::query()->create([
                'organization_id' => $organization->id,
                'name' => $rule['name'],
                'description' => $rule['description'],
                'metric_type' => $rule['metric_type'],
                'condition' => AlertCondition::Gt,
                'threshold' => $rule['threshold'],
                'duration' => $rule['duration'],
                'severity' => $rule['severity'],
                'enabled' => true,
                'notification_channels' => [
                    ['channel' => 'mail', 'target' => 'it@acme.test'],
                ],
            ]);
        }

        app(EvaluateAlertsAction::class)->handle($organization);
    }

    private function seedIncidents(Organization $organization): void
    {
        Incident::query()->where('organization_id', $organization->id)->delete();

        $admin = User::query()->where('email', 'admin@acme.test')->first();

        if ($admin === null) {
            return;
        }

        $alert = Alert::query()
            ->where('organization_id', $organization->id)
            ->whereIn('severity', [AlertSeverity::High, AlertSeverity::Critical])
            ->orderByDesc('id')
            ->first();

        $create = app(CreateIncidentAction::class);
        $update = app(UpdateIncidentAction::class);
        $record = app(RecordIncidentEventAction::class);

        if ($alert !== null) {
            $incident = $create->handle($organization, [
                'title' => $alert->title,
                'description' => $alert->description,
                'severity' => $alert->severity,
                'host_id' => $alert->host_id,
                'assigned_to' => $admin->id,
            ], $admin, $alert);

            $update->handle($incident, [
                'status' => IncidentStatus::Investigating,
                'assigned_to' => $admin->id,
            ], $admin);

            $record->handle(
                $incident,
                IncidentEventType::Comment,
                'Checking disk usage on the database host.',
                $admin,
            );
        }

        $host = Host::query()
            ->where('organization_id', $organization->id)
            ->where('hostname', 'db-01.acme.test')
            ->first();

        $create->handle($organization, [
            'title' => 'Manual follow-up: database disk',
            'description' => 'Opened from the operations shift after the disk alert.',
            'severity' => AlertSeverity::High,
            'priority' => IncidentPriority::P2,
            'host_id' => $host?->id,
        ], $admin);
    }

    private function seedDashboards(Organization $organization): void
    {
        Dashboard::query()->where('organization_id', $organization->id)->delete();

        $admin = User::query()->where('email', 'admin@acme.test')->first();

        if ($admin === null) {
            return;
        }

        app(CreateDashboardAction::class)->handle($organization, [
            'name' => 'Infrastructure',
            'description' => 'Starter view of hosts, metrics, alerts, and incidents.',
            'is_default' => true,
            'seed_layout' => true,
        ], $admin);
    }

    private function seedApplications(Organization $organization): void
    {
        $web = Host::query()
            ->where('organization_id', $organization->id)
            ->where('hostname', 'web-01.acme.test')
            ->first();
        $staging = Host::query()
            ->where('organization_id', $organization->id)
            ->where('hostname', 'stg-api-01.acme.test')
            ->first();

        $orders = Application::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => 'orders-api',
                'environment' => HostEnvironment::Production->value,
            ],
            [
                'name' => 'orders-api',
                'type' => ApplicationType::Laravel,
                'version' => '1.4.2',
                'endpoint' => 'https://api.acme.test/orders',
                'description' => 'Order intake API.',
                'host_id' => $web?->id,
            ],
        );

        $checkout = Application::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'slug' => 'checkout-web',
                'environment' => HostEnvironment::Staging->value,
            ],
            [
                'name' => 'checkout-web',
                'type' => ApplicationType::NodeJs,
                'version' => '3.2.0',
                'endpoint' => 'https://checkout.staging.acme.test',
                'description' => 'Checkout frontend.',
                'host_id' => $staging?->id,
            ],
        );

        $this->seedApplicationMetrics($organization, $orders, 420, 3, 182, 421);
        $this->seedApplicationMetrics($organization, $checkout, 180, 12, 310, 1180);
    }

    private function seedApplicationMetrics(Organization $organization, Application $application, int $requests, int $errors, int $avg, int $p95): void
    {
        ApplicationMetric::query()
            ->withoutGlobalScopes()
            ->where('application_id', $application->id)
            ->delete();

        $rows = [];
        $now = now();

        for ($offset = 359; $offset >= 0; $offset--) {
            $collectedAt = $now->copy()->subMinutes($offset);
            $cycle = sin($offset / 40) * 0.16;
            $ripple = sin($offset / 9) * 0.05;
            $jitter = ((($offset * 53) % 17) - 8) / 180;
            $factor = 1 + $cycle + $ripple + $jitter;

            $requestCount = max(4, (int) round(($requests / 5) * $factor));
            $errorCount = min($requestCount, max(0, (int) round(($errors / 5) * max(0.45, $factor))));
            $avgMs = max(40, (int) round($avg * (1 + $ripple + $jitter * 0.5)));
            $p95Ms = max($avgMs + 24, (int) round($p95 * (1 + ($cycle * 0.22) + $jitter)));

            $rows[] = [
                'organization_id' => $organization->id,
                'application_id' => $application->id,
                'request_count' => $requestCount,
                'error_count' => $errorCount,
                'response_time_avg' => $avgMs,
                'response_time_p95' => $p95Ms,
                'status_codes' => json_encode([
                    '200' => max(0, $requestCount - $errorCount),
                    '500' => $errorCount,
                ]),
                'collected_at' => $collectedAt,
                'created_at' => $collectedAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ApplicationMetric::query()->insert($chunk);
        }

        $latest = $rows[array_key_last($rows)];

        $application->forceFill([
            'status' => ApmCatalog::statusFromSample(
                (int) $latest['request_count'],
                (int) $latest['error_count'],
                (int) $latest['response_time_p95'],
            ),
            'last_seen_at' => $now,
        ])->save();
    }
}
