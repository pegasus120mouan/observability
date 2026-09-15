<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DashboardCatalog;
use Illuminate\Support\Facades\DB;

class CreateDashboardAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, User $actor): Dashboard
    {
        return DB::transaction(function () use ($organization, $data, $actor): Dashboard {
            $isDefault = (bool) ($data['is_default'] ?? false);

            if ($isDefault) {
                $this->clearDefault($organization->id);
            }

            $dashboard = Dashboard::query()->create([
                'organization_id' => $organization->id,
                'created_by' => $actor->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
            ]);

            if (! empty($data['seed_layout'])) {
                foreach (DashboardCatalog::starterWidgets() as $index => $widget) {
                    DashboardWidget::query()->create([
                        'organization_id' => $organization->id,
                        'dashboard_id' => $dashboard->id,
                        'type' => $widget['type'],
                        'title' => $widget['title'],
                        'width' => $widget['width'],
                        'sort_order' => $index,
                        'config' => $widget['config'],
                    ]);
                }
            }

            $this->auditLogger->log(AuditAction::DashboardCreated, $dashboard, newValues: [
                'name' => $dashboard->name,
                'is_default' => $dashboard->is_default,
            ], organization: $organization, actor: $actor);

            return $dashboard;
        });
    }

    private function clearDefault(int $organizationId): void
    {
        Dashboard::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
