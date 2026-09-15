<?php

namespace App\Actions;

use App\Enums\IncidentEventType;
use App\Models\Incident;
use App\Models\IncidentEvent;
use App\Models\User;

class RecordIncidentEventAction
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function handle(
        Incident $incident,
        IncidentEventType $type,
        string $message,
        ?User $actor = null,
        ?array $metadata = null,
    ): IncidentEvent {
        return IncidentEvent::query()->withoutGlobalScopes()->create([
            'organization_id' => $incident->organization_id,
            'incident_id' => $incident->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }
}
