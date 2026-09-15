<?php

namespace App\Actions;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Host;
use Illuminate\Support\Str;

class DiscoverHostServicesAction
{
    /**
     * @param  list<array{name: string, type: string, version?: string|null}>  $services
     * @return array{applications: int, stopped: int}
     */
    public function handle(Agent $agent, array $services): array
    {
        $agent->loadMissing('host');
        $host = $agent->host;

        abort_if($host === null, 422, 'This agent is not linked to a host.');

        $now = now();
        $seenIds = [];

        foreach ($services as $payload) {
            $application = $this->upsert($agent, $host, $payload);
            $application->forceFill([
                'discovered' => true,
                'status' => ApplicationStatus::Healthy,
                'last_seen_at' => $now,
            ])->save();
            $seenIds[] = $application->id;
        }

        $stopped = Application::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $agent->organization_id)
            ->where('host_id', $host->id)
            ->where('discovered', true)
            ->when($seenIds !== [], fn ($query) => $query->whereNotIn('id', $seenIds))
            ->when($seenIds === [], fn ($query) => $query)
            ->update(['status' => ApplicationStatus::Unknown->value]);

        return [
            'applications' => count($seenIds),
            'stopped' => $stopped,
        ];
    }

    /**
     * @param  array{name: string, type: string, version?: string|null}  $payload
     */
    private function upsert(Agent $agent, Host $host, array $payload): Application
    {
        $name = (string) $payload['name'];
        $slug = Str::slug($name.'-'.$host->hostname) ?: 'service';

        $application = Application::query()
            ->withoutGlobalScopes()
            ->firstOrNew([
                'organization_id' => $agent->organization_id,
                'slug' => $slug,
                'environment' => $host->environment->value,
            ]);

        $application->fill([
            'name' => $name,
            'type' => $payload['type'] instanceof ApplicationType
                ? $payload['type']
                : ApplicationType::from((string) $payload['type']),
            'version' => $payload['version'] ?? $application->version,
            'host_id' => $host->id,
            'description' => $application->description ?: 'Discovered by the host agent.',
        ]);

        if (! $application->exists) {
            $application->status = ApplicationStatus::Healthy;
            $application->discovered = true;
        }

        $application->save();

        return $application;
    }
}
