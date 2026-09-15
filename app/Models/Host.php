<?php

namespace App\Models;

use App\Enums\HostEnvironment;
use App\Enums\HostStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\HostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'agent_id',
    'hostname',
    'display_name',
    'ip_address',
    'operating_system',
    'os_version',
    'architecture',
    'environment',
    'status',
    'last_seen_at',
    'registered_at',
])]
class Host extends Model
{
    /** @use HasFactory<HostFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'environment' => 'production',
        'status' => 'offline',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => HostEnvironment::class,
            'status' => HostStatus::class,
            'last_seen_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return HasMany<MetricSample, $this>
     */
    public function metricSamples(): HasMany
    {
        return $this->hasMany(MetricSample::class);
    }

    /**
     * @return HasMany<LogSource, $this>
     */
    public function logSources(): HasMany
    {
        return $this->hasMany(LogSource::class);
    }

    /**
     * @return HasMany<LogEntry, $this>
     */
    public function logEntries(): HasMany
    {
        return $this->hasMany(LogEntry::class);
    }

    /**
     * @return HasMany<Alert, $this>
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function displayName(): string
    {
        return $this->display_name ?: $this->hostname;
    }

    public function isOnline(): bool
    {
        return $this->status === HostStatus::Online;
    }

    #[Scope]
    protected function online(Builder $query): Builder
    {
        return $query->where('status', HostStatus::Online);
    }
}
