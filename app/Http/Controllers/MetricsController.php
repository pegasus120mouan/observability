<?php

namespace App\Http\Controllers;

use App\Enums\MetricType;
use App\Models\Host;
use App\Services\MetricQuery;
use App\Support\MetricCatalog;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MetricsController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext, MetricQuery $metricQuery): View
    {
        $this->authorize('viewAny', Host::class);

        abort_if($tenantContext->organization() === null && ! $request->user()?->isSuperAdmin(), 404);

        $hosts = Host::query()->orderBy('hostname')->orderBy('id')->get();
        $selectedHost = $hosts->firstWhere('id', $request->integer('host_id')) ?? $hosts->first();

        $metricKey = (string) $request->query('metric', 'cpu.usage');
        [$typeValue, $name] = array_pad(explode('.', $metricKey, 2), 2, 'usage');
        $type = MetricType::tryFrom($typeValue) ?? MetricType::Cpu;
        $name = $name !== '' ? $name : 'usage';

        if (! MetricCatalog::isAllowed($type, $name)) {
            $type = MetricType::Cpu;
            $name = 'usage';
        }

        $range = $this->rangeHours($request->query('range', '6h'));
        $from = now()->subHours($range);
        $chart = $selectedHost === null
            ? ['label' => MetricCatalog::label($type, $name), 'unit' => MetricCatalog::unit($type, $name), 'labels' => [], 'values' => []]
            : $metricQuery->chart($selectedHost, $type, $name, $from, now());

        return view('metrics.index', [
            'hosts' => $hosts,
            'selectedHost' => $selectedHost,
            'type' => $type,
            'name' => $name,
            'metricKey' => $type->value.'.'.$name,
            'range' => $request->query('range', '6h'),
            'chart' => $chart,
            'catalog' => MetricCatalog::chartable(),
        ]);
    }

    private function rangeHours(mixed $range): int
    {
        return match ($range) {
            '1h' => 1,
            '24h' => 24,
            default => 6,
        };
    }
}
