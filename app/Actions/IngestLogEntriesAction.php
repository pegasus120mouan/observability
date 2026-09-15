<?php

namespace App\Actions;

use App\Enums\LogLevel;
use App\Enums\LogSourceStatus;
use App\Enums\LogSourceType;
use App\Models\Agent;
use App\Models\Host;
use App\Models\LogEntry;
use App\Models\LogSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class IngestLogEntriesAction
{
    /**
     * @param  list<array<string, mixed>>  $entries
     * @return array{inserted: int, skipped: int, host: Host}
     */
    public function handle(Agent $agent, array $entries, ?Carbon $fallbackLoggedAt = null): array
    {
        $agent->loadMissing('host');
        $host = $agent->host;

        abort_if($host === null, 422, 'This agent is not linked to a host.');

        $fallbackLoggedAt ??= now();
        $now = now();
        $rows = [];
        $skipped = 0;
        $sources = [];

        foreach ($entries as $entry) {
            $sourceName = $this->sourceName($entry);
            $source = $sources[$sourceName] ??= $this->sourceFor($agent, $host, $sourceName);

            if ($source->isPaused()) {
                $skipped++;

                continue;
            }

            $level = LogLevel::from(strtolower((string) $entry['level']));
            $loggedAt = isset($entry['timestamp'])
                ? Carbon::parse((string) $entry['timestamp'])
                : $fallbackLoggedAt;

            $rows[] = [
                'organization_id' => $agent->organization_id,
                'host_id' => $host->id,
                'source_id' => $source->id,
                'logged_at' => $loggedAt,
                'level' => $level->value,
                'message' => Str::limit((string) $entry['message'], 8000, ''),
                'source' => $sourceName,
                'facility' => $entry['facility'] ?? null,
                'event_id' => isset($entry['event_id']) ? (string) $entry['event_id'] : null,
                'ip_address' => $entry['ip_address'] ?? null,
                'username' => $entry['user'] ?? $entry['username'] ?? null,
                'process' => $entry['process'] ?? null,
                'metadata' => isset($entry['metadata']) ? json_encode($entry['metadata']) : null,
                'created_at' => $now,
            ];
        }

        if ($rows !== []) {
            foreach (array_chunk($rows, 100) as $chunk) {
                LogEntry::query()->withoutGlobalScopes()->insert($chunk);
            }
        }

        $host->forceFill(['last_seen_at' => $now])->save();

        return [
            'inserted' => count($rows),
            'skipped' => $skipped,
            'host' => $host->fresh(),
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function sourceName(array $entry): string
    {
        $name = trim((string) ($entry['source'] ?? 'agent'));

        return $name !== '' ? Str::limit($name, 100, '') : 'agent';
    }

    private function sourceFor(Agent $agent, Host $host, string $name): LogSource
    {
        return LogSource::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'organization_id' => $agent->organization_id,
                    'host_id' => $host->id,
                    'name' => $name,
                ],
                [
                    'type' => LogSourceType::Agent,
                    'status' => LogSourceStatus::Active,
                    'configuration' => [],
                ],
            );
    }
}
