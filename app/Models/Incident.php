<?php

namespace App\Models;

use App\Enums\AlertSeverity;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'host_id',
    'title',
    'description',
    'severity',
    'status',
    'priority',
    'assigned_to',
    'detected_at',
    'resolved_at',
    'closed_at',
    'root_cause',
    'resolution',
])]
class Incident extends Model
{
    /** @use HasFactory<IncidentFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'priority' => 'p2',
        'severity' => 'high',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => AlertSeverity::class,
            'status' => IncidentStatus::class,
            'priority' => IncidentPriority::class,
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function reference(): string
    {
        return 'INC-'.$this->id;
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return HasMany<IncidentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(IncidentEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function isClosed(): bool
    {
        return $this->status === IncidentStatus::Closed;
    }
}
