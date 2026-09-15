<?php

namespace App\Console\Commands\Logs;

use App\Models\LogEntry;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('logs:prune')]
#[Description('Delete log entries older than each organization retention window')]
class PruneLogsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;

        Organization::query()
            ->orderBy('id')
            ->each(function (Organization $organization) use (&$deleted): void {
                $cutoff = now()->subDays(max(1, (int) $organization->log_retention_days));

                $deleted += LogEntry::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('logged_at', '<', $cutoff)
                    ->delete();
            });

        $this->info("Pruned {$deleted} log entries.");

        return self::SUCCESS;
    }
}
