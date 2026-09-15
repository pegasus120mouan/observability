<?php

namespace App\Http\Controllers;

use App\Enums\AlertStatus;
use App\Enums\HostStatus;
use App\Enums\IncidentStatus;
use App\Enums\MetricType;
use App\Models\Agent;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Host;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Services\LogQuery;
use App\Services\MetricQuery;
use App\Support\TenantContext;
use Illuminate\View\View;

class OverviewController extends Controller
{
    public function __invoke(TenantContext $tenantContext, MetricQuery $metricQuery, LogQuery $logQuery): View
    {
        $organization = $tenantContext->organization();
        $user = request()->user();

        $usersQuery = $organization
            ? $organization->users()
            : User::query();

        $auditQuery = AuditLog::query()->with('user')->latest('id');

        if ($organization !== null) {
            $auditQuery->where('organization_id', $organization->id);
        } elseif (! $user?->isSuperAdmin()) {
            $auditQuery->whereRaw('1 = 0');
        }

        $cpuChart = $organization
            ? $metricQuery->organizationAverage(MetricType::Cpu, 'usage', now()->subHours(6))
            : ['label' => 'CPU', 'unit' => 'percent', 'labels' => [], 'values' => []];

        return view('overview', [
            'organization' => $organization,
            'userCount' => $usersQuery->count(),
            'organizationCount' => $user?->isSuperAdmin()
                ? Organization::query()->count()
                : $user?->organizations()->count(),
            'adminCount' => $organization
                ? $organization->memberships()->whereHas('role', fn ($query) => $query->where('name', 'ADMIN'))->count()
                : 0,
            'hostCount' => $organization ? Host::query()->count() : 0,
            'hostOnlineCount' => $organization ? Host::query()->where('status', HostStatus::Online)->count() : 0,
            'agentCount' => $organization ? Agent::query()->count() : 0,
            'cpuChart' => $cpuChart,
            'errorLogCount' => $organization ? $logQuery->problemCountSince(now()->subDay()) : 0,
            'openAlertCount' => $organization
                ? Alert::query()->whereIn('status', [AlertStatus::Open, AlertStatus::Acknowledged])->count()
                : 0,
            'openIncidentCount' => $organization
                ? Incident::query()->whereIn('status', [
                    IncidentStatus::Open,
                    IncidentStatus::Investigating,
                    IncidentStatus::Mitigated,
                ])->count()
                : 0,
            'recentIncidents' => $organization
                ? Incident::query()->with('host')->orderByDesc('detected_at')->orderByDesc('id')->limit(5)->get()
                : collect(),
            'recentAuditLogs' => $auditQuery->limit(8)->get(),
        ]);
    }
}
