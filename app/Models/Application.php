<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\HostEnvironment;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'host_id',
    'name',
    'slug',
    'type',
    'environment',
    'version',
    'endpoint',
    'description',
    'status',
    'last_seen_at',
])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'environment' => 'production',
        'status' => 'unknown',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ApplicationType::class,
            'environment' => HostEnvironment::class,
            'status' => ApplicationStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    /**
     * @return HasMany<ApplicationMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(ApplicationMetric::class);
    }
}
