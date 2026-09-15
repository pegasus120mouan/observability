<?php

namespace App\Actions;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Enums\IncidentEventType;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Alert;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CreateIncidentAction
{
    public function __construct(
        private RecordIncidentEventAction $recordEvent,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, User $actor, ?Alert $alert = null): Incident
    {
        return DB::transaction(function () use ($organization, $data, $actor, $alert): Incident {
            if ($alert !== null) {
                $alert = Alert::query()->withoutGlobalScopes()
                    ->whereKey($alert->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($alert->incident_id) {
                    return Incident::query()->withoutGlobalScopes()->findOrFail($alert->incident_id);
                }
            }

            $severity = $data['severity'] instanceof AlertSeverity
                ? $data['severity']
                : AlertSeverity::from((string) $data['severity']);

            $priority = isset($data['priority'])
                ? ($data['priority'] instanceof IncidentPriority ? $data['priority'] : IncidentPriority::from((string) $data['priority']))
                : IncidentPriority::fromSeverity($severity);

            $incident = Incident::query()->create([
                'organization_id' => $organization->id,
                'host_id' => $data['host_id'] ?? $alert?->host_id,
                'title' => $data['title'],
                'description' => $data['description'] ?? $alert?->description,
                'severity' => $severity,
                'status' => IncidentStatus::Open,
                'priority' => $priority,
                'assigned_to' => $data['assigned_to'] ?? null,
                'detected_at' => $data['detected_at'] ?? $alert?->triggered_at ?? now(),
            ]);

            $this->recordEvent->handle(
                $incident,
                IncidentEventType::Created,
                $actor->name.' opened this incident.',
                $actor,
            );

            if ($alert !== null) {
                $alert->forceFill(['incident_id' => $incident->id])->save();
                $this->recordEvent->handle(
                    $incident,
                    IncidentEventType::AlertLinked,
                    'Linked alert: '.$alert->title,
                    $actor,
                    ['alert_id' => $alert->id],
                );
            }

            if (! empty($data['assigned_to'])) {
                $assignee = User::query()->find($data['assigned_to']);
                $this->recordEvent->handle(
                    $incident,
                    IncidentEventType::Assigned,
                    'Assigned to '.($assignee?->name ?? 'a teammate').'.',
                    $actor,
                    ['assigned_to' => $data['assigned_to']],
                );
            }

            $this->auditLogger->log(AuditAction::IncidentCreated, $incident, newValues: [
                'title' => $incident->title,
                'severity' => $incident->severity->value,
            ], organization: $organization, actor: $actor);

            return $incident;
        });
    }
}
