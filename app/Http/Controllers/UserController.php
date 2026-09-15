<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAction;
use App\Actions\UpdateUserAction;
use App\Enums\AuditAction;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $organization = $this->requireOrganization();

        $users = $organization->users()
            ->with(['memberships' => fn ($query) => $query->where('organization_id', $organization->id)->with('role')])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return view('users.index', [
            'users' => $users,
            'organization' => $organization,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'roles' => RoleName::organizationRoles(),
            'statuses' => UserStatus::cases(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $action->handle($this->requireOrganization(), $request->validated(), $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        $member = $this->member($user);

        $this->authorize('update', $member);

        return view('users.edit', [
            'member' => $member,
            'roles' => RoleName::organizationRoles(),
            'statuses' => UserStatus::cases(),
            'currentRole' => $member->roleIn($this->requireOrganization())?->name,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $member = $this->member($user);
        $action->handle($this->requireOrganization(), $member, $request->validated(), $request->user());

        return redirect()
            ->route('users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $member = $this->member($user);
        $this->authorize('delete', $member);

        $organization = $this->requireOrganization();

        $auditLogger->log(
            AuditAction::UserDeleted,
            $member,
            oldValues: ['email' => $member->email],
            organization: $organization,
        );

        $organization->users()->detach($member->id);

        if ($member->organizations()->doesntExist() && ! $member->isSuperAdmin()) {
            $member->delete();
        }

        return redirect()
            ->route('users.index')
            ->with('status', 'User removed from the organization.');
    }

    private function requireOrganization()
    {
        $organization = $this->tenantContext->organization();

        abort_if($organization === null, 404);

        return $organization;
    }

    private function member(User $user): User
    {
        return $this->requireOrganization()
            ->users()
            ->whereKey($user->id)
            ->firstOrFail();
    }
}
