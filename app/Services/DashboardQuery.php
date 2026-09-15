<?php

namespace App\Services;

use App\Enums\AlertStatus;
use App\Enums\DashboardStatMetric;
use App\Enums\DashboardWidgetType;
use App\Enums\HostStatus;
use App\Enums\IncidentStatus;
use App\Enums\MetricType;
use App\Models\Alert;
use App\Models\Dashboard;
use App\Models\DashboardWidget;
use App\Models\Host;
use App\Models\Incident;
use App\Models\LogEntry;
use App\Support\DashboardCatalog;
use App\Support\MetricCatalog;
use Illuminate\Support\Collection;

class DashboardQuery
{
    public function __construct(
        private MetricQuery $metricQuery,
        private LogQuery $logQuery,
    ) {}

    /**
     * @return list<array{widget: DashboardWidget, width: int, payload: array<string, mixed>}>
     */
    public function render(Dashboard $dashboard): array
    {
        $widgets = $dashboard->widgets()->get();
        $stats = $this->statsNeeded($widgets);
        $timeseriesHosts = $this->timeseriesHosts($widgets);

        $listLimits = $this->maxLimits($widgets);
        $hosts = $this->hosts($listLimits[DashboardWidgetType::Hosts->value] ?? 0);
        $alerts = $this->alerts($listLimits[DashboardWidgetType::Alerts->value] ?? 0);
        $incidents = $this->incidents($listLimits[DashboardWidgetType::Incidents->value] ?? 0);
        $logs = $this->logs($listLimits[DashboardWidgetType::Logs->value] ?? 0);

        $rows = [];

        foreach ($widgets as $widget) {
            $rows[] = [
                'widget' => $widget,
                'width' => in_array($widget->width, DashboardCatalog::widths(), true) ? $widget->width : 6,
                'payload' => $this->payload($widget, $stats, $timeseriesHosts, $hosts, $alerts, $incidents, $logs),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, DashboardWidget>  $widgets
     * @return array<string, int>
     */
    private function statsNeeded(Collection $widgets): array
    {
        $needed = [];

        foreach ($widgets as $widget) {
            if ($widget->type !== DashboardWidgetType::Stat) {
                continue;
            }

            $metric = DashboardStatMetric::tryFrom((string) ($widget->config['metric'] ?? ''));

            if ($metric !== null) {
                $needed[$metric->value] = true;
            }
        }

        if ($needed === []) {
            return [];
        }

        $values = [];

        if (isset($needed[DashboardStatMetric::Hosts->value]) || isset($needed[DashboardStatMetric::HostsOnline->value])) {
            $values[DashboardStatMetric::Hosts->value] = Host::query()->count();
            $values[DashboardStatMetric::HostsOnline->value] = Host::query()->where('status', HostStatus::Online)->count();
        }

        if (isset($needed[DashboardStatMetric::OpenAlerts->value])) {
            $values[DashboardStatMetric::OpenAlerts->value] = Alert::query()
                ->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])
                ->count();
        }

        if (isset($needed[DashboardStatMetric::OpenIncidents->value])) {
            $values[DashboardStatMetric::OpenIncidents->value] = Incident::query()
                ->whereIn('status', [
                    IncidentStatus::Open,
                    IncidentStatus::Investigating,
                    IncidentStatus::Mitigated,
                ])
                ->count();
        }

        if (isset($needed[DashboardStatMetric::ErrorLogs->value])) {
            $values[DashboardStatMetric::ErrorLogs->value] = $this->logQuery->problemCountSince(now()->subDay());
        }

        return $values;
    }

    /**
     * @param  Collection<int, DashboardWidget>  $widgets
     * @return array<string, int>
     */
    private function maxLimits(Collection $widgets): array
    {
        $limits = [];

        foreach ($widgets as $widget) {
            if (! in_array($widget->type, [
                DashboardWidgetType::Hosts,
                DashboardWidgetType::Alerts,
                DashboardWidgetType::Incidents,
                DashboardWidgetType::Logs,
            ], true)) {
                continue;
            }

            $limit = max(1, min(50, $widget->configInt('limit', 8)));
            $key = $widget->type->value;
            $limits[$key] = max($limits[$key] ?? 0, $limit);
        }

        return $limits;
    }

    /**
     * @return Collection<int, Host>
     */
    private function hosts(int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Host::query()->orderBy('hostname')->orderBy('id')->limit($limit)->get();
    }

    /**
     * @return Collection<int, Alert>
     */
    private function alerts(int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Alert::query()
            ->with('host')
            ->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Incident>
     */
    private function incidents(int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return Incident::query()
            ->with('host')
            ->orderByDesc('detected_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, LogEntry>
     */
    private function logs(int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return LogEntry::query()
            ->with('host')
            ->orderByDesc('logged_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Collection<int, DashboardWidget>  $widgets
     * @return Collection<int, Host>
     */
    private function timeseriesHosts(Collection $widgets): Collection
    {
        $hostIds = $widgets
            ->filter(fn (DashboardWidget $widget): bool => $widget->type === DashboardWidgetType::Timeseries)
            ->map(fn (DashboardWidget $widget): int => $widget->configInt('host_id', 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($hostIds === []) {
            return collect();
        }

        return Host::query()->whereIn('id', $hostIds)->get()->keyBy('id');
    }

    /**
     * @param  array<string, int>  $stats
     * @param  Collection<int, Host>  $timeseriesHosts
     * @param  Collection<int, Host>  $hosts
     * @param  Collection<int, Alert>  $alerts
     * @param  Collection<int, Incident>  $incidents
     * @param  Collection<int, LogEntry>  $logs
     * @return array<string, mixed>
     */
    private function payload(
        DashboardWidget $widget,
        array $stats,
        Collection $timeseriesHosts,
        Collection $hosts,
        Collection $alerts,
        Collection $incidents,
        Collection $logs,
    ): array {
        $limit = max(1, min(50, $widget->configInt('limit', 8)));

        return match ($widget->type) {
            DashboardWidgetType::Stat => $this->statPayload($widget, $stats),
            DashboardWidgetType::Timeseries => $this->timeseriesPayload($widget, $timeseriesHosts),
            DashboardWidgetType::Hosts => ['hosts' => $hosts->take($limit)->values()],
            DashboardWidgetType::Alerts => ['alerts' => $alerts->take($limit)->values()],
            DashboardWidgetType::Incidents => ['incidents' => $incidents->take($limit)->values()],
            DashboardWidgetType::Logs => ['logs' => $logs->take($limit)->values()],
        };
    }

    /**
     * @param  array<string, int>  $stats
     * @return array<string, mixed>
     */
    private function statPayload(DashboardWidget $widget, array $stats): array
    {
        $metric = DashboardStatMetric::tryFrom((string) ($widget->config['metric'] ?? ''))
            ?? DashboardStatMetric::Hosts;

        return [
            'label' => $widget->title !== '' ? $widget->title : $metric->label(),
            'value' => $stats[$metric->value] ?? 0,
            'hint' => $metric->hint(),
            'icon' => $metric->icon(),
        ];
    }

    /**
     * @param  Collection<int, Host>  $timeseriesHosts
     * @return array<string, mixed>
     */
    private function timeseriesPayload(DashboardWidget $widget, Collection $timeseriesHosts): array
    {
        $type = MetricType::tryFrom((string) ($widget->config['metric_type'] ?? MetricType::Cpu->value))
            ?? MetricType::Cpu;
        $name = $widget->configString('metric_name', 'usage') ?? 'usage';

        if (! MetricCatalog::isAllowed($type, $name)) {
            $name = array_key_first(MetricCatalog::namesFor($type)) ?? 'usage';
        }

        $hours = DashboardCatalog::hoursForRange($widget->configString('range', '6h') ?? '6h');
        $from = now()->subHours($hours);
        $hostId = $widget->configInt('host_id', 0);
        $host = $hostId > 0 ? $timeseriesHosts->get($hostId) : null;

        $chart = $host instanceof Host
            ? $this->metricQuery->chart($host, $type, $name, $from, now())
            : $this->metricQuery->organizationAverage($type, $name, $from);

        return [
            'chart' => $chart,
            'hint' => $host instanceof Host
                ? $host->displayName().' · last '.$hours.'h'
                : 'Organization average · last '.$hours.'h',
        ];
    }
}
