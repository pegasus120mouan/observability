<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $descriptions = [
            RoleName::SuperAdmin->value => 'Full platform access across every organization',
            RoleName::Admin->value => 'Manages users, agents, hosts, dashboards, alerts, and incidents for one organization',
            RoleName::Analyst->value => 'Investigates logs, metrics, alerts, and incidents',
            RoleName::Operator->value => 'Operates monitoring, alerts, and incidents',
            RoleName::Viewer->value => 'Read-only access to the organization',
        ];

        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleName],
                [
                    'description' => $descriptions[$roleName->value],
                    'is_system' => true,
                ],
            );

            $permissionIds = Permission::query()
                ->whereIn('name', PermissionCatalog::forRole($roleName))
                ->pluck('id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
