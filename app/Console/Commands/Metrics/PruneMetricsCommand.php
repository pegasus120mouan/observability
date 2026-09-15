<?php

namespace App\Console\Commands\Metrics;

use App\Models\ApplicationMetric;
use App\Models\MetricSample;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('metrics:prune')]
#[Description('Delete metric and application samples older than each organization retention window')]
class PruneMetricsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;
        $apmDeleted = 0;

        Organization::query()
            ->orderBy('id')
            ->each(function (Organization $organization) use (&$deleted, &$apmDeleted): void {
                $cutoff = now()->subDays(max(1, (int) $organization->metric_retention_days));

                $deleted += MetricSample::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('collected_at', '<', $cutoff)
                    ->delete();

                $apmDeleted += ApplicationMetric::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $organization->id)
                    ->where('collected_at', '<', $cutoff)
                    ->delete();
            });

        $this->info("Pruned {$deleted} metric samples.");
        $this->info("Pruned {$apmDeleted} application metrics.");

        return self::SUCCESS;
    }
}
