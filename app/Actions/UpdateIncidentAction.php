<?php

namespace App\Actions;

use App\Enums\AlertSeverity;
use App\Enums\AuditAction;
use App\Enums\IncidentEventType;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateIncidentAction
{
    public function __construct(
        private RecordIncidentEventAction $recordEvent,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Incident $incident, array $data, User $actor): Incident
    {
        if ($incident->isClosed()) {
            throw ValidationException::withMessages([
                'status' => 'A closed incident cannot be updated.',
            ]);
        }

        return DB::transaction(function () use ($incident, $data, $actor): Incident {
            $previous = $incident->only(['status', 'assigned_to', 'priority', 'severity', 'root_cause', 'resolution']);

            if (isset($data['status'])) {
                $next = $data['status'] instanceof IncidentStatus
                    ? $data['status']
                    : IncidentStatus::from((string) $data['status']);

                if (! $incident->status->canTransitionTo($next)) {
                    throw ValidationException::withMessages([
                        'status' => 'Incidents cannot move backward from '.$incident->status->label().'.',
                    ]);
                }

                if ($next !== $incident->status) {
                    $from = $incident->status;
                    $incident->status = $next;

                    if ($next === IncidentStatus::Resolved && $incident->resolved_at === null) {
                        $incident->resolved_at = now();
                    }

                    if ($next === IncidentStatus::Closed && $incident->closed_at === null) {
                        $incident->closed_at = now();
                        $incident->resolved_at ??= now();
                    }

                    $this->recordEvent->handle(
                        $incident,
                        IncidentEventType::StatusChanged,
                        'Status changed from '.$from->label().' to '.$next->label().'.',
                        $actor,
                        ['from' => $from->value, 'to' => $next->value],
                    );
                }
            }

            if (array_key_exists('assigned_to', $data) && (int) $data['assigned_to'] !== (int) $incident->assigned_to) {
                $incident->assigned_to = $data['assigned_to'] ?: null;
                $assignee = $incident->assigned_to ? User::query()->find($incident->assigned_to) : null;
                $this->recordEvent->handle(
                    $incident,
                    IncidentEventType::Assigned,
                    $assignee ? 'Assigned to '.$assignee->name.'.' : 'Cleared the assignee.',
                    $actor,
                    ['assigned_to' => $incident->assigned_to],
                );
            }

            foreach (['title', 'description', 'root_cause', 'resolution'] as $field) {
                if (array_key_exists($field, $data)) {
                    $incident->{$field} = $data[$field];
                }
            }

            if (isset($data['priority'])) {
                $incident->priority = $data['priority'] instanceof IncidentPriority
                    ? $data['priority']
                    : IncidentPriority::from((string) $data['priority']);
            }

            if (isset($data['severity'])) {
                $incident->severity = $data['severity'] instanceof AlertSeverity
                    ? $data['severity']
                    : AlertSeverity::from((string) $data['severity']);
            }

            $dirty = $incident->isDirty();
            $incident->save();

            if (! $dirty) {
                return $incident->fresh(['assignee', 'host', 'alerts']) ?? $incident;
            }

            if (! $incident->wasChanged('status') && ! $incident->wasChanged('assigned_to')) {
                $this->recordEvent->handle(
                    $incident,
                    IncidentEventType::Updated,
                    $actor->name.' updated incident details.',
                    $actor,
                );
            }

            $this->auditLogger->log(
                $incident->wasChanged('status') && in_array($incident->status, [IncidentStatus::Resolved, IncidentStatus::Closed], true)
                    ? AuditAction::IncidentResolved
                    : AuditAction::IncidentUpdated,
                $incident,
                oldValues: $previous,
                newValues: $incident->only(['status', 'assigned_to', 'priority', 'severity', 'root_cause', 'resolution']),
                actor: $actor,
            );

            return $incident->fresh(['assignee', 'host', 'alerts']) ?? $incident;
        });
    }
}
