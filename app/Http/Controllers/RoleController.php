<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(request()->user()?->hasPermission(PermissionCatalog::ROLES_VIEW), 403);

        $roles = Role::query()
            ->with('permissions')
            ->orderBy('id')
            ->get();

        return view('roles.index', [
            'roles' => $roles,
            'catalog' => PermissionCatalog::all(),
            'assignments' => collect(RoleName::cases())
                ->mapWithKeys(fn (RoleName $role) => [$role->value => PermissionCatalog::forRole($role)]),
        ]);
    }
}
