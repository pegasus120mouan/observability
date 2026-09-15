<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class AuditLogger
{
    /**
     * @var list<string>
     */
    private array $sensitiveKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'api_key',
        'api_secret',
        'secret',
        'credentials',
        'enrollment_token',
        'token_hash',
        'api_key_hash',
    ];

    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        AuditAction $action,
        ?Model $resource = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Organization $organization = null,
        ?User $actor = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= $request->user();
        $organization ??= $this->tenantContext->organization();

        if ($organization === null && $actor?->current_organization_id) {
            $organization = Organization::query()->find($actor->current_organization_id);
        }

        return AuditLog::query()->withoutGlobalScopes()->create([
            'organization_id' => $organization?->id,
            'user_id' => $actor?->id,
            'action' => $action,
            'resource_type' => $resource === null ? null : $resource->getMorphClass(),
            'resource_id' => $resource?->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $this->sanitize($oldValues),
            'new_values' => $this->sanitize($newValues),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return Arr::except($values, $this->sensitiveKeys);
    }
}
