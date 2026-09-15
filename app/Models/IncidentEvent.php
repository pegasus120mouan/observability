<?php

namespace App\Models;

use App\Enums\IncidentEventType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\IncidentEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'incident_id',
    'user_id',
    'type',
    'message',
    'metadata',
])]
class IncidentEvent extends Model
{
    /** @use HasFactory<IncidentEventFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IncidentEventType::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Incident, $this>
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
