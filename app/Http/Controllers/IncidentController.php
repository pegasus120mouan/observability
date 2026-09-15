<?php

namespace App\Http\Controllers;

use App\Actions\CreateIncidentAction;
use App\Actions\RecordIncidentEventAction;
use App\Actions\UpdateIncidentAction;
use App\Enums\AlertSeverity;
use App\Enums\IncidentEventType;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Http\Requests\StoreIncidentCommentRequest;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Models\Alert;
use App\Models\Host;
use App\Models\Incident;
use App\Models\Organization;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', Incident::class);

        abort_if($tenantContext->organization() === null && ! $request->user()?->isSuperAdmin(), 404);

        $query = Incident::query()
            ->with(['host', 'assignee'])
            ->orderByDesc('detected_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $status = IncidentStatus::tryFrom((string) $request->query('status'));

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

        if ($request->filled('priority')) {
            $priority = IncidentPriority::tryFrom((string) $request->query('priority'));

            if ($priority !== null) {
                $query->where('priority', $priority);
            }
        }

        $counts = Incident::query()
            ->toBase()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('incidents.index', [
            'incidents' => $query->paginate(20)->withQueryString(),
            'statuses' => IncidentStatus::cases(),
            'severities' => AlertSeverity::cases(),
            'priorities' => IncidentPriority::cases(),
            'filters' => $request->only(['status', 'severity', 'priority']),
            'open' => (int) ($counts[IncidentStatus::Open->value] ?? 0),
            'investigating' => (int) ($counts[IncidentStatus::Investigating->value] ?? 0),
            'mitigated' => (int) ($counts[IncidentStatus::Mitigated->value] ?? 0),
            'resolved' => (int) ($counts[IncidentStatus::Resolved->value] ?? 0),
        ]);
    }

    public function create(TenantContext $tenantContext): View
    {
        $this->authorize('create', Incident::class);

        $organization = $tenantContext->organization();
        abort_if($organization === null, 404);

        return view('incidents.create', $this->formData($organization->id));
    }

    public function store(StoreIncidentRequest $request, TenantContext $tenantContext, CreateIncidentAction $action): RedirectResponse
    {
        $organization = $tenantContext->organization();
        abort_if($organization === null, 404);

        $incident = $action->handle($organization, $request->validated(), $request->user());

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident opened.');
    }

    public function show(Incident $incident, TenantContext $tenantContext): View
    {
        $this->authorize('view', $incident);

        $incident->load(['host', 'assignee', 'alerts.host', 'events.user']);

        $organization = $tenantContext->organization();

        return view('incidents.show', [
            ...($organization ? $this->formData($organization->id) : [
                'hosts' => collect(),
                'members' => collect(),
                'statuses' => IncidentStatus::cases(),
                'severities' => AlertSeverity::cases(),
                'priorities' => IncidentPriority::cases(),
            ]),
            'incident' => $incident,
        ]);
    }

    public function update(UpdateIncidentRequest $request, Incident $incident, UpdateIncidentAction $action): RedirectResponse
    {
        $this->authorize('update', $incident);

        $action->handle($incident, $request->validated(), $request->user());

        return back()->with('status', 'Incident updated.');
    }

    public function comment(StoreIncidentCommentRequest $request, Incident $incident, RecordIncidentEventAction $recordEvent): RedirectResponse
    {
        $this->authorize('update', $incident);

        if ($incident->isClosed()) {
            return back()->withErrors(['message' => 'A closed incident cannot receive comments.']);
        }

        $recordEvent->handle(
            $incident,
            IncidentEventType::Comment,
            $request->validated('message'),
            $request->user(),
        );

        return back()->with('status', 'Comment added.');
    }

    public function fromAlert(Alert $alert, TenantContext $tenantContext, CreateIncidentAction $action): RedirectResponse
    {
        $this->authorize('update', $alert);
        $this->authorize('create', Incident::class);

        $organization = $tenantContext->organization();
        abort_if($organization === null || $alert->organization_id !== $organization->id, 404);

        if ($alert->incident_id) {
            return redirect()->route('incidents.show', $alert->incident_id)
                ->with('status', 'This alert is already linked to an incident.');
        }

        $incident = $action->handle($organization, [
            'title' => $alert->title,
            'description' => $alert->description,
            'severity' => $alert->severity,
            'host_id' => $alert->host_id,
        ], request()->user(), $alert);

        return redirect()->route('incidents.show', $incident)->with('status', 'Incident opened from alert.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(int $organizationId): array
    {
        return [
            'hosts' => Host::query()->orderBy('hostname')->orderBy('id')->get(),
            'members' => Organization::query()->findOrFail($organizationId)
                ->users()
                ->orderBy('name')
                ->orderBy('id')
                ->get(),
            'statuses' => IncidentStatus::cases(),
            'severities' => AlertSeverity::cases(),
            'priorities' => IncidentPriority::cases(),
        ];
    }
}
