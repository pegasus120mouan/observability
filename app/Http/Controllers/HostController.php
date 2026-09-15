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
use Illuminate\Http\JsonResponse;
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
            now()->subHours(6),
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

        $from = now()->subHours($this->rangeHours($request->query('range', '6h')));
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

    public function live(Request $request, Host $host, MetricQuery $metricQuery): JsonResponse
    {
        $this->authorize('view', $host);

        return response()->json($metricQuery->liveSnapshot(
            $host,
            now()->subHours($this->rangeHours($request->query('range', '6h'))),
            now(),
        ));
    }

    public function liveIndex(Request $request, TenantContext $tenantContext, MetricQuery $metricQuery): JsonResponse
    {
        $this->authorize('viewAny', Host::class);

        abort_if($tenantContext->organization() === null && $request->user()?->isSuperAdmin() !== true, 404);

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn (string $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->take(50)
            ->values();

        $hosts = Host::query()
            ->whereIn('id', $ids->all())
            ->orderBy('id')
            ->get();

        return response()->json([
            'hosts' => $hosts->mapWithKeys(function (Host $host) use ($metricQuery): array {
                $usage = $metricQuery->latestUsage($host);

                return [
                    $host->id => [
                        'cpu' => $usage['cpu'] ?? null,
                        'memory' => $usage['memory'] ?? null,
                        'disk' => $usage['disk'] ?? null,
                        'status' => $host->status->value,
                        'status_label' => $host->status->label(),
                        'status_variant' => $host->status->badgeVariant(),
                        'last_seen_at' => $host->last_seen_at?->diffForHumans(),
                    ],
                ];
            }),
        ]);
    }

    private function rangeHours(?string $range): int
    {
        return match ($range) {
            '1h' => 1,
            '24h' => 24,
            default => 6,
        };
    }
}
