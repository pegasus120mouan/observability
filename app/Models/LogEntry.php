<?php

namespace App\Models;

use App\Enums\LogLevel;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\LogEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'host_id',
    'source_id',
    'logged_at',
    'level',
    'message',
    'source',
    'facility',
    'event_id',
    'ip_address',
    'username',
    'process',
    'metadata',
])]
class LogEntry extends Model
{
    /** @use HasFactory<LogEntryFactory> */
    use BelongsToOrganization, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'logs';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => LogLevel::class,
            'logged_at' => 'datetime',
            'metadata' => 'array',
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
     * @return BelongsTo<LogSource, $this>
     */
    public function logSource(): BelongsTo
    {
        return $this->belongsTo(LogSource::class, 'source_id');
    }
}
