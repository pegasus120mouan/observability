<?php

namespace Tests\Feature;

use App\Actions\EvaluateAlertsAction;
use App\Enums\AlertMetric;
use App\Enums\HostStatus;
use App\Models\AlertRule;
use App\Models\Organization;
use App\Notifications\AlertTriggeredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPlatform();
    }

    public function test_mail_channel_is_notified_when_an_alert_opens(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $this->createMonitoredHost($organization, [
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subMinutes(20),
        ]);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::HostOffline,
            'duration' => 5,
            'notification_channels' => [
                ['channel' => 'mail', 'target' => 'ops@acme.test'],
            ],
        ]);

        app(EvaluateAlertsAction::class)->handle($organization);

        Notification::assertSentOnDemand(
            AlertTriggeredNotification::class,
            function (AlertTriggeredNotification $notification, array $channels, object $notifiable): bool {
                return in_array('mail', $channels, true)
                    && ($notifiable->routes['mail'] ?? null) === 'ops@acme.test';
            },
        );
    }

    public function test_webhook_channel_posts_the_payload(): void
    {
        Http::fake([
            'https://hooks.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $organization = Organization::factory()->create();
        $this->createMonitoredHost($organization, [
            'hostname' => 'hook-host',
            'status' => HostStatus::Offline,
            'last_seen_at' => now()->subMinutes(20),
        ]);
        AlertRule::factory()->create([
            'organization_id' => $organization->id,
            'metric_type' => AlertMetric::HostOffline,
            'duration' => 5,
            'notification_channels' => [
                ['channel' => 'webhook', 'target' => 'https://hooks.example.test/saha'],
            ],
        ]);

        app(EvaluateAlertsAction::class)->handle($organization);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://hooks.example.test/saha'
                && $request['event'] === 'alert.triggered'
                && str_contains((string) $request['alert']['title'], 'hook-host');
        });
    }
}
