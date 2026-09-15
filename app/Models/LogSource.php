<?php

namespace App\Models;

use App\Enums\LogSourceStatus;
use App\Enums\LogSourceType;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\LogSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'host_id',
    'name',
    'type',
    'configuration',
    'status',
])]
class LogSource extends Model
{
    /** @use HasFactory<LogSourceFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'agent',
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LogSourceType::class,
            'status' => LogSourceStatus::class,
            'configuration' => 'array',
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
     * @return HasMany<LogEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(LogEntry::class, 'source_id');
    }

    public function isPaused(): bool
    {
        return $this->status === LogSourceStatus::Paused;
    }
}
