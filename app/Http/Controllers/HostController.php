<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Enums\HostStatus;
use App\Enums\MetricType;
use App\Http\Requests\UpdateHostRequest;
use App\Models\Alert;
use App\Models\Application;
use App\Models\Host;
use App\Services\LogQuery;
use App\Services\MetricQuery;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HostController extends Controller
{
    public function index(TenantContext $tenantContext, MetricQuery $metricQuery): View
    {
        $this->authorize('viewAny', Host::class);

        abort_if($tenantContext->organization() === null && ! request()->user()?->isSuperAdmin(), 404);

        $hosts = Host::query()
            ->with('agent')
            ->orderBy('hostname')
            ->orderBy('id')
            ->paginate(20);

        $latest = $metricQuery->latestUsageByHost(
            $hosts->pluck('id')->all(),
            now()->subMinutes(15),
        );

        $counts = Host::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (Host $row): array => [$row->status->value => (int) $row->aggregate]);

        return view('hosts.index', [
            'hosts' => $hosts,
            'latest' => $latest,
            'online' => (int) ($counts[HostStatus::Online->value] ?? 0),
            'warning' => (int) ($counts[HostStatus::Warning->value] ?? 0),
            'critical' => (int) ($counts[HostStatus::Critical->value] ?? 0),
            'offline' => (int) ($counts[HostStatus::Offline->value] ?? 0),
        ]);
    }

    public function show(Request $request, Host $host, MetricQuery $metricQuery, LogQuery $logQuery): View
    {
        $this->authorize('view', $host);

        $host->load('agent');

        $hours = match ($request->query('range', '6h')) {
            '1h' => 1,
            '24h' => 24,
            default => 6,
        };
        $from = now()->subHours($hours);
        $to = now();

        return view('hosts.show', [
            'host' => $host,
            'range' => $request->query('range', '6h'),
            'usage' => $metricQuery->latestUsage($host),
            'cpuChart' => $metricQuery->chart($host, MetricType::Cpu, 'usage', $from, $to),
            'memoryChart' => $metricQuery->chart($host, MetricType::Memory, 'usage', $from, $to),
            'diskChart' => $metricQuery->chart($host, MetricType::Disk, 'usage', $from, $to),
            'networkInChart' => $metricQuery->chart($host, MetricType::Network, 'rx_bytes', $from, $to),
            'networkOutChart' => $metricQuery->chart($host, MetricType::Network, 'tx_bytes', $from, $to),
            'recentLogs' => $logQuery->recentForHost($host),
            'recentAlerts' => Alert::query()
                ->where('host_id', $host->id)
                ->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])
                ->orderByDesc('triggered_at')
                ->orderByDesc('id')
                ->limit(5)
                ->get(),
            'applications' => Application::query()
                ->where('host_id', $host->id)
                ->orderBy('name')
                ->orderBy('id')
                ->limit(8)
                ->get(),
        ]);
    }

    public function update(UpdateHostRequest $request, Host $host): RedirectResponse
    {
        $this->authorize('update', $host);

        $host->update($request->safe()->only(['display_name', 'environment']));

        return back()->with('status', 'Host updated.');
    }
}
