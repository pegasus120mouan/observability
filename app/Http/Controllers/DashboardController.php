<?php

namespace App\Http\Controllers;

use App\Actions\CreateDashboardAction;
use App\Actions\UpdateDashboardAction;
use App\Enums\AuditAction;
use App\Http\Requests\StoreDashboardRequest;
use App\Http\Requests\UpdateDashboardRequest;
use App\Models\Dashboard;
use App\Services\AuditLogger;
use App\Services\DashboardQuery;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', Dashboard::class);

        abort_if($tenantContext->organization() === null && ! request()->user()?->isSuperAdmin(), 404);

        $dashboards = Dashboard::query()
            ->withCount('widgets')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        return view('dashboards.index', [
            'dashboards' => $dashboards,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Dashboard::class);

        return view('dashboards.create');
    }

    public function store(StoreDashboardRequest $request, TenantContext $tenantContext, CreateDashboardAction $action): RedirectResponse
    {
        $organization = $tenantContext->organization();
        abort_if($organization === null, 404);

        $dashboard = $action->handle($organization, [
            ...$request->validated(),
            'is_default' => $request->boolean('is_default'),
            'seed_layout' => $request->boolean('seed_layout'),
        ], $request->user());

        return redirect()->route('dashboards.show', $dashboard)->with('status', 'Dashboard created.');
    }

    public function show(Dashboard $dashboard, DashboardQuery $query): View
    {
        $this->authorize('view', $dashboard);

        return view('dashboards.show', [
            'dashboard' => $dashboard,
            'widgets' => $query->render($dashboard),
        ]);
    }

    public function edit(Dashboard $dashboard): View
    {
        $this->authorize('update', $dashboard);

        $dashboard->load('widgets');

        return view('dashboards.edit', [
            'dashboard' => $dashboard,
            ...DashboardWidgetController::formData(),
        ]);
    }

    public function update(UpdateDashboardRequest $request, Dashboard $dashboard, UpdateDashboardAction $action): RedirectResponse
    {
        $this->authorize('update', $dashboard);

        $action->handle($dashboard, [
            ...$request->validated(),
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->route('dashboards.edit', $dashboard)->with('status', 'Dashboard updated.');
    }

    public function destroy(Dashboard $dashboard, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('delete', $dashboard);

        $auditLogger->log(AuditAction::DashboardDeleted, $dashboard, oldValues: [
            'name' => $dashboard->name,
        ]);

        $dashboard->delete();

        return redirect()->route('dashboards.index')->with('status', 'Dashboard deleted.');
    }
}
