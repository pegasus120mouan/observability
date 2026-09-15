<?php

namespace App\Actions;

use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateUserAction
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($organization, $user, $data, $actor): User {
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status?->value,
                'role' => $user->roleIn($organization)?->name->value,
            ];

            $user->fill(Arr::only($data, ['name', 'email', 'status']));

            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();

            if (isset($data['role'])) {
                $roleName = $data['role'] instanceof RoleName
                    ? $data['role']
                    : RoleName::from($data['role']);

                if (! $roleName->isAssignableToOrganization()) {
                    throw new InvalidArgumentException('The SUPER_ADMIN role cannot be assigned to an organization membership.');
                }

                $role = Role::query()->where('name', $roleName)->firstOrFail();

                $organization->users()->updateExistingPivot($user->id, [
                    'role_id' => $role->id,
                ]);
            }

            $user = $user->fresh();

            $this->auditLogger->log(
                empty($data['password']) ? AuditAction::UserUpdated : AuditAction::PasswordChanged,
                $user,
                oldValues: $oldValues,
                newValues: [
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status?->value,
                    'role' => $user->roleIn($organization)?->name->value,
                ],
                organization: $organization,
                actor: $actor,
            );

            return $user;
        });
    }
}
