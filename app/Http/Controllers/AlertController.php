<?php

namespace App\Http\Controllers;

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AuditAction;
use App\Models\Alert;
use App\Models\Host;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', Alert::class);

        abort_if($tenantContext->organization() === null && ! $request->user()?->isSuperAdmin(), 404);

        $query = Alert::query()
            ->with(['host', 'rule'])
            ->orderByDesc('triggered_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = AlertStatus::tryFrom((string) $request->query('status'));

            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('severity')) {
            $severity = AlertSeverity::tryFrom((string) $request->query('severity'));

            if ($severity !== null) {
                $query->where('severity', $severity);
            }
        }

        if ($request->filled('host_id')) {
            $query->where('host_id', $request->integer('host_id'));
        }

        if ($request->filled('from')) {
            $query->where('triggered_at', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('triggered_at', '<=', $request->date('to'));
        }

        $counts = Alert::query()
            ->toBase()
            ->selectRaw('severity, COUNT(*) as aggregate')
            ->whereIn('status', [AlertStatus::Open->value, AlertStatus::Acknowledged->value])
            ->groupBy('severity')
            ->pluck('aggregate', 'severity');

        return view('alerts.index', [
            'alerts' => $query->paginate(20)->withQueryString(),
            'hosts' => Host::query()->orderBy('hostname')->orderBy('id')->get(),
            'statuses' => AlertStatus::cases(),
            'severities' => AlertSeverity::cases(),
            'filters' => $request->only(['status', 'severity', 'host_id', 'from', 'to']),
            'critical' => (int) ($counts[AlertSeverity::Critical->value] ?? 0),
            'high' => (int) ($counts[AlertSeverity::High->value] ?? 0),
            'medium' => (int) ($counts[AlertSeverity::Medium->value] ?? 0),
            'low' => (int) ($counts[AlertSeverity::Low->value] ?? 0),
        ]);
    }

    public function show(Alert $alert): View
    {
        $this->authorize('view', $alert);

        $alert->load(['host', 'rule', 'acknowledgedBy', 'resolvedBy']);

        return view('alerts.show', [
            'alert' => $alert,
        ]);
    }

    public function acknowledge(Alert $alert, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $alert);

        if (! $alert->isActive()) {
            return back()->with('status', 'This alert is already resolved.');
        }

        $alert->forceFill([
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => request()->user()?->id,
        ])->save();

        $auditLogger->log(AuditAction::AlertAcknowledged, $alert, oldValues: [
            'status' => AlertStatus::Open->value,
        ], newValues: [
            'status' => AlertStatus::Acknowledged->value,
        ]);

        return back()->with('status', 'Alert acknowledged.');
    }

    public function resolve(Alert $alert, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $alert);

        if ($alert->status === AlertStatus::Resolved || $alert->status === AlertStatus::Closed) {
            return back()->with('status', 'This alert is already resolved.');
        }

        $previous = $alert->status->value;

        $alert->forceFill([
            'status' => AlertStatus::Resolved,
            'resolved_at' => now(),
            'resolved_by' => request()->user()?->id,
        ])->save();

        $auditLogger->log(AuditAction::AlertResolved, $alert, oldValues: [
            'status' => $previous,
        ], newValues: [
            'status' => AlertStatus::Resolved->value,
        ]);

        return back()->with('status', 'Alert resolved.');
    }
}
