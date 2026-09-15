<?php

namespace App\Console\Commands\Alerts;

use App\Jobs\EvaluateAlertJob;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('alerts:evaluate')]
#[Description('Evaluate enabled alert rules against current host metrics, logs, and heartbeat age')]
class EvaluateAlertsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dispatched = 0;

        Organization::query()
            ->orderBy('id')
            ->each(function (Organization $organization) use (&$dispatched): void {
                EvaluateAlertJob::dispatch($organization->id);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} alert evaluation jobs.");

        return self::SUCCESS;
    }
}
