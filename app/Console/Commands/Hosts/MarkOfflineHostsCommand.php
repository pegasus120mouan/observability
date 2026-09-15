<?php

namespace App\Console\Commands\Hosts;

use App\Enums\AgentStatus;
use App\Enums\HostStatus;
use App\Models\Agent;
use App\Models\Host;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hosts:mark-offline')]
#[Description('Mark hosts and agents offline when heartbeats stop')]
class MarkOfflineHostsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $threshold = now()->subMinutes((int) config('platform.agents.offline_after_minutes'));
        $hostsUpdated = 0;
        $agentsUpdated = 0;

        Host::query()
            ->withoutGlobalScopes()
            ->whereNot('status', HostStatus::Maintenance)
            ->whereNot('status', HostStatus::Offline)
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $threshold);
            })
            ->chunkById(100, function ($hosts) use (&$hostsUpdated): void {
                foreach ($hosts as $host) {
                    $host->forceFill(['status' => HostStatus::Offline])->save();
                    $hostsUpdated++;
                }
            });

        Agent::query()
            ->withoutGlobalScopes()
            ->whereNot('status', AgentStatus::Revoked)
            ->whereNot('status', AgentStatus::Offline)
            ->where(function ($query) use ($threshold): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $threshold);
            })
            ->chunkById(100, function ($agents) use (&$agentsUpdated): void {
                foreach ($agents as $agent) {
                    $agent->forceFill(['status' => AgentStatus::Offline])->save();
                    $agentsUpdated++;
                }
            });

        $this->info("Marked {$hostsUpdated} hosts and {$agentsUpdated} agents offline.");

        return self::SUCCESS;
    }
}
