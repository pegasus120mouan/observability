<?php

namespace App\Http\Controllers;

use App\Actions\CreateApplicationAction;
use App\Actions\UpdateApplicationAction;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\HostEnvironment;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Models\Application;
use App\Models\Host;
use App\Services\ApmQuery;
use App\Services\AuditLogger;
use App\Support\ApmCatalog;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(TenantContext $tenantContext, ApmQuery $query): View
    {
        $this->authorize('viewAny', Application::class);

        abort_if($tenantContext->organization() === null && ! request()->user()?->isSuperAdmin(), 404);

        $applications = Application::query()
            ->with('host')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        $latest = $query->latestByApplication($applications->pluck('id')->all());

        $counts = Application::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (Application $row): array => [$row->status->value => (int) $row->aggregate]);

        return view('applications.index', [
            'applications' => $applications,
            'latest' => $latest,
            'healthy' => (int) ($counts[ApplicationStatus::Healthy->value] ?? 0),
            'warning' => (int) ($counts[ApplicationStatus::Warning->value] ?? 0),
            'critical' => (int) ($counts[ApplicationStatus::Critical->value] ?? 0),
            'unknown' => (int) ($counts[ApplicationStatus::Unknown->value] ?? 0),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Application::class);

        return view('applications.create', $this->formData());
    }

    public function store(StoreApplicationRequest $request, TenantContext $tenantContext, CreateApplicationAction $action): RedirectResponse
    {
        $organization = $tenantContext->organization();
        abort_if($organization === null, 404);

        $application = $action->handle($organization, $request->validated(), $request->user());

        return redirect()->route('applications.show', $application)->with('status', 'Application created.');
    }

    public function show(Request $request, Application $application, ApmQuery $query): View
    {
        $this->authorize('view', $application);

        $application->load('host');
        $range = $this->resolvedRange($request);
        $snapshot = $query->snapshot($application, ApmCatalog::fromForRange($range), $range);

        return view('applications.show', [
            'application' => $application,
            'range' => $range,
            'summary' => $snapshot['summary'],
            'charts' => $snapshot['charts'],
            'recent' => $snapshot['recent'],
            'hasHttpSamples' => $snapshot['has_http_samples'],
            'runtime' => $application->runtime_stats ?? [],
            'health' => $snapshot['health'],
            'usageMap' => $snapshot['usage_map'],
        ]);
    }

    public function live(Request $request, Application $application, ApmQuery $query): JsonResponse
    {
        $this->authorize('view', $application);

        $application->refresh();
        $range = $this->resolvedRange($request);
        $snapshot = $query->snapshot($application, ApmCatalog::fromForRange($range), $range);

        return response()->json([
            ...$snapshot,
            'runtime' => $application->runtime_stats ?? [],
            'status' => $snapshot['health']['status'],
            'status_label' => $snapshot['health']['status_label'],
            'status_variant' => $snapshot['health']['status_variant'],
            'last_seen_at' => $application->last_seen_at?->diffForHumans(),
        ]);
    }

    public function edit(Application $application): View
    {
        $this->authorize('update', $application);

        $application->load('host');

        return view('applications.edit', [
            ...$this->formData(),
            'application' => $application,
        ]);
    }

    public function update(UpdateApplicationRequest $request, Application $application, UpdateApplicationAction $action): RedirectResponse
    {
        $this->authorize('update', $application);

        $action->handle($application, $request->validated());

        return redirect()->route('applications.edit', $application)->with('status', 'Application updated.');
    }

    public function destroy(Application $application, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('delete', $application);

        $auditLogger->log(AuditAction::ApplicationDeleted, $application, oldValues: [
            'name' => $application->name,
        ]);

        $application->delete();

        return redirect()->route('applications.index')->with('status', 'Application deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'types' => ApplicationType::cases(),
            'environments' => HostEnvironment::cases(),
            'hosts' => Host::query()->orderBy('hostname')->orderBy('id')->get(),
        ];
    }

    private function resolvedRange(Request $request): string
    {
        $range = (string) $request->query('range', ApmCatalog::defaultRange());

        return array_key_exists($range, ApmCatalog::ranges())
            ? $range
            : ApmCatalog::defaultRange();
    }
}
