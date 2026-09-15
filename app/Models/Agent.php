<?php

namespace App\Models;

use App\Enums\AgentPlatform;
use App\Enums\AgentStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\AgentCredentials;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'host_id',
    'name',
    'agent_uid',
    'api_key_hash',
    'version',
    'platform',
    'architecture',
    'status',
    'last_seen_at',
    'installed_at',
    'revoked_at',
])]
#[Hidden(['api_key_hash'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use BelongsToOrganization, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => AgentPlatform::class,
            'status' => AgentStatus::class,
            'last_seen_at' => 'datetime',
            'installed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function isRevoked(): bool
    {
        return $this->status === AgentStatus::Revoked || $this->revoked_at !== null;
    }

    public function apiKeyMatches(string $plainText): bool
    {
        return hash_equals($this->api_key_hash, AgentCredentials::hash($plainText));
    }
}
