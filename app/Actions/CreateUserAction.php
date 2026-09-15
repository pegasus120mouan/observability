<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateUserAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, User $actor): User
    {
        $roleName = $data['role'] instanceof RoleName
            ? $data['role']
            : RoleName::from($data['role']);

        if (! $roleName->isAssignableToOrganization()) {
            throw new InvalidArgumentException('The SUPER_ADMIN role cannot be assigned to an organization membership.');
        }

        return DB::transaction(function () use ($organization, $data, $actor, $roleName): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $data['status'] ?? UserStatus::Active,
                'current_organization_id' => $organization->id,
            ]);

            $role = Role::query()->where('name', $roleName)->firstOrFail();

            $organization->users()->attach($user->id, [
                'role_id' => $role->id,
                'status' => MembershipStatus::Active->value,
            ]);

            $this->auditLogger->log(
                AuditAction::UserCreated,
                $user,
                newValues: [
                    'email' => $user->email,
                    'role' => $roleName->value,
                ],
                organization: $organization,
                actor: $actor,
            );

            return $user;
        });
    }
}
