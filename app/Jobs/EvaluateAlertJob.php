<?php

namespace App\Jobs;

use App\Actions\EvaluateAlertsAction;
use App\Models\Organization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAlertJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $organizationId) {}

    public function handle(EvaluateAlertsAction $action): void
    {
        $organization = Organization::query()->find($this->organizationId);

        if ($organization === null) {
            return;
        }

        $action->handle($organization);
    }
}
