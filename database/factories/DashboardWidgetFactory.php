<?php

namespace Database\Factories;

use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DashboardWidget>
 */
class DashboardWidgetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dashboard_id' => Dashboard::factory(),
            'organization_id' => fn (array $attributes): mixed => Dashboard::query()->find($attributes['dashboard_id'])?->organization_id,
            'type' => DashboardWidgetType::Stat,
            'title' => DashboardStatMetric::OpenAlerts->label(),
            'sort_order' => 0,
            'width' => 3,
            'config' => ['metric' => DashboardStatMetric::OpenAlerts->value],
        ];
    }

    public function forDashboard(Dashboard $dashboard): static
    {
        return $this->state(fn (array $attributes): array => [
            'organization_id' => $dashboard->organization_id,
            'dashboard_id' => $dashboard->id,
        ]);
    }
}
