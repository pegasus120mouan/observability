<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Dashboard;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class UpdateDashboardAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Dashboard $dashboard, array $data): Dashboard
    {
        return DB::transaction(function () use ($dashboard, $data): Dashboard {
            $previous = $dashboard->only(['name', 'description', 'is_default']);
            $isDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : $dashboard->is_default;

            if ($isDefault) {
                Dashboard::query()
                    ->withoutGlobalScopes()
                    ->where('organization_id', $dashboard->organization_id)
                    ->where('id', '!=', $dashboard->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $dashboard->fill([
                'name' => $data['name'] ?? $dashboard->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $dashboard->description,
                'is_default' => $isDefault,
            ])->save();

            $this->auditLogger->log(
                AuditAction::DashboardUpdated,
                $dashboard,
                oldValues: $previous,
                newValues: $dashboard->only(['name', 'description', 'is_default']),
            );

            return $dashboard->fresh() ?? $dashboard;
        });
    }
}
