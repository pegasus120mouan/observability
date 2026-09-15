<?php

namespace App\Http\Controllers;

use App\Enums\AlertCondition;
use App\Enums\AlertMetric;
use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Http\Requests\StoreAlertRuleRequest;
use App\Http\Requests\UpdateAlertRuleRequest;
use App\Models\AlertRule;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AlertRuleController extends Controller
{
    public function index(TenantContext $tenantContext): View
    {
        $this->authorize('viewAny', AlertRule::class);

        abort_if($tenantContext->organization() === null && ! request()->user()?->isSuperAdmin(), 404);

        $rules = AlertRule::query()
            ->withCount(['alerts as open_alerts_count' => fn ($query) => $query->whereIn('status', ['open', 'acknowledged'])])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20);

        return view('alert-rules.index', [
            'rules' => $rules,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', AlertRule::class);

        return view('alert-rules.create', $this->formData());
    }

    public function store(StoreAlertRuleRequest $request, TenantContext $tenantContext, AuditLogger $auditLogger): RedirectResponse
    {
        $organization = $tenantContext->organization();

        abort_if($organization === null, 404);

        $rule = AlertRule::query()->create([
            ...$this->payload($request),
            'organization_id' => $organization->id,
        ]);

        $auditLogger->log(AuditAction::AlertRuleCreated, $rule, newValues: [
            'name' => $rule->name,
            'metric_type' => $rule->metric_type->value,
        ]);

        return redirect()->route('alert-rules.index')->with('status', 'Alert rule created.');
    }

    public function edit(AlertRule $alertRule): View
    {
        $this->authorize('update', $alertRule);

        return view('alert-rules.edit', [
            ...$this->formData(),
            'rule' => $alertRule,
        ]);
    }

    public function update(UpdateAlertRuleRequest $request, AlertRule $alertRule, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('update', $alertRule);

        $previous = $alertRule->only(['name', 'metric_type', 'threshold', 'enabled']);
        $alertRule->update($this->payload($request));

        $auditLogger->log(AuditAction::AlertRuleUpdated, $alertRule, oldValues: $previous, newValues: $alertRule->only(['name', 'metric_type', 'threshold', 'enabled']));

        return redirect()->route('alert-rules.index')->with('status', 'Alert rule updated.');
    }

    public function destroy(AlertRule $alertRule, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorize('delete', $alertRule);

        $auditLogger->log(AuditAction::AlertRuleDeleted, $alertRule, oldValues: [
            'name' => $alertRule->name,
        ]);

        $alertRule->delete();

        return redirect()->route('alert-rules.index')->with('status', 'Alert rule deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'metrics' => AlertMetric::cases(),
            'conditions' => AlertCondition::cases(),
            'severities' => AlertSeverity::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreAlertRuleRequest $request): array
    {
        $metric = AlertMetric::from($request->validated('metric_type'));

        return [
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'metric_type' => $metric,
            'condition' => $metric->usesThreshold()
                ? $request->enum('condition', AlertCondition::class)
                : AlertCondition::Gte,
            'threshold' => $metric->usesThreshold() ? $request->validated('threshold') : null,
            'duration' => $request->integer('duration'),
            'severity' => $request->enum('severity', AlertSeverity::class),
            'enabled' => $request->boolean('enabled'),
            'notification_channels' => $request->validated('notification_channels') ?? [],
        ];
    }
}
