<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Support\PermissionCatalog;
use App\Support\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'status', 'is_super_admin', 'current_organization_id', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'is_super_admin' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'status' => UserStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot(['id', 'role_id', 'status'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'current_organization_id');
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin === true;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        if ($this->relationLoaded('organizations')) {
            return $this->organizations->contains('id', $organization->id);
        }

        return $this->organizations()->whereKey($organization->id)->exists();
    }

    public function membershipFor(Organization $organization): ?OrganizationUser
    {
        if ($this->relationLoaded('memberships')) {
            return $this->memberships->firstWhere('organization_id', $organization->id);
        }

        return $this->memberships()->where('organization_id', $organization->id)->first();
    }

    public function roleIn(?Organization $organization = null): ?Role
    {
        $organization ??= app(TenantContext::class)->organization();

        if ($organization === null) {
            return null;
        }

        $membership = $this->membershipFor($organization);

        if ($membership === null) {
            return null;
        }

        if ($membership->relationLoaded('role')) {
            return $membership->role;
        }

        return $membership->role()->first();
    }

    public function hasRole(RoleName $role, ?Organization $organization = null): bool
    {
        if ($role === RoleName::SuperAdmin) {
            return $this->isSuperAdmin();
        }

        return $this->roleIn($organization)?->name === $role;
    }

    public function hasPermission(string $permission, ?Organization $organization = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $role = $this->roleIn($organization);

        return $role !== null && in_array($permission, PermissionCatalog::forRole($role->name), true);
    }

    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', UserStatus::Active);
    }
}
