<?php

namespace App\Support;

use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Enums\MetricType;

final class DashboardCatalog
{
    /**
     * @return list<int>
     */
    public static function widths(): array
    {
        return [3, 4, 6, 12];
    }

    /**
     * @return array<string, int>
     */
    public static function ranges(): array
    {
        return [
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
        ];
    }

    public static function hoursForRange(string $range): int
    {
        return self::ranges()[$range] ?? 6;
    }

    /**
     * @return list<array{type: DashboardWidgetType, title: string, width: int, config: array<string, mixed>}>
     */
    public static function starterWidgets(): array
    {
        return [
            [
                'type' => DashboardWidgetType::Stat,
                'title' => DashboardStatMetric::HostsOnline->label(),
                'width' => 3,
                'config' => ['metric' => DashboardStatMetric::HostsOnline->value],
            ],
            [
                'type' => DashboardWidgetType::Stat,
                'title' => DashboardStatMetric::OpenAlerts->label(),
                'width' => 3,
                'config' => ['metric' => DashboardStatMetric::OpenAlerts->value],
            ],
            [
                'type' => DashboardWidgetType::Stat,
                'title' => DashboardStatMetric::OpenIncidents->label(),
                'width' => 3,
                'config' => ['metric' => DashboardStatMetric::OpenIncidents->value],
            ],
            [
                'type' => DashboardWidgetType::Stat,
                'title' => DashboardStatMetric::ErrorLogs->label(),
                'width' => 3,
                'config' => ['metric' => DashboardStatMetric::ErrorLogs->value],
            ],
            [
                'type' => DashboardWidgetType::Timeseries,
                'title' => 'CPU usage',
                'width' => 6,
                'config' => ['metric_type' => MetricType::Cpu->value, 'metric_name' => 'usage', 'range' => '6h'],
            ],
            [
                'type' => DashboardWidgetType::Timeseries,
                'title' => 'Memory usage',
                'width' => 6,
                'config' => ['metric_type' => MetricType::Memory->value, 'metric_name' => 'usage', 'range' => '6h'],
            ],
            [
                'type' => DashboardWidgetType::Alerts,
                'title' => 'Active alerts',
                'width' => 6,
                'config' => ['limit' => 8],
            ],
            [
                'type' => DashboardWidgetType::Incidents,
                'title' => 'Recent incidents',
                'width' => 6,
                'config' => ['limit' => 5],
            ],
        ];
    }
}
