<?php

namespace App\Actions;

use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Enums\MetricType;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Support\DashboardCatalog;
use App\Support\MetricCatalog;

class SaveDashboardWidgetAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Dashboard $dashboard, array $data, ?DashboardWidget $widget = null): DashboardWidget
    {
        $type = $data['type'] instanceof DashboardWidgetType
            ? $data['type']
            : DashboardWidgetType::from((string) $data['type']);

        $payload = [
            'organization_id' => $dashboard->organization_id,
            'dashboard_id' => $dashboard->id,
            'type' => $type,
            'title' => $data['title'],
            'width' => (int) ($data['width'] ?? 6),
            'sort_order' => array_key_exists('sort_order', $data) && $data['sort_order'] !== null
                ? (int) $data['sort_order']
                : ($widget?->sort_order ?? $this->nextSortOrder($dashboard)),
            'config' => $this->config($type, $data),
        ];

        if ($widget === null) {
            return DashboardWidget::query()->create($payload);
        }

        $widget->fill($payload)->save();

        return $widget->fresh() ?? $widget;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function config(DashboardWidgetType $type, array $data): array
    {
        return match ($type) {
            DashboardWidgetType::Stat => [
                'metric' => $data['stat_metric'] instanceof DashboardStatMetric
                    ? $data['stat_metric']->value
                    : (string) $data['stat_metric'],
            ],
            DashboardWidgetType::Timeseries => $this->timeseriesConfig($data),
            DashboardWidgetType::Hosts,
            DashboardWidgetType::Alerts,
            DashboardWidgetType::Incidents,
            DashboardWidgetType::Logs => [
                'limit' => max(1, min(50, (int) ($data['limit'] ?? 8))),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function timeseriesConfig(array $data): array
    {
        $type = $data['metric_type'] instanceof MetricType
            ? $data['metric_type']
            : MetricType::from((string) $data['metric_type']);
        $name = (string) ($data['metric_name'] ?? 'usage');

        if (! MetricCatalog::isAllowed($type, $name)) {
            $name = array_key_first(MetricCatalog::namesFor($type)) ?? 'usage';
        }

        $range = (string) ($data['range'] ?? '6h');

        if (! array_key_exists($range, DashboardCatalog::ranges())) {
            $range = '6h';
        }

        $config = [
            'metric_type' => $type->value,
            'metric_name' => $name,
            'range' => $range,
        ];

        if (! empty($data['host_id'])) {
            $config['host_id'] = (int) $data['host_id'];
        }

        return $config;
    }

    private function nextSortOrder(Dashboard $dashboard): int
    {
        return (int) $dashboard->widgets()->max('sort_order') + 1;
    }
}
