<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private TenantContext $tenantContext) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $organization = $this->requireOrganization();

        $users = $organization->users()
            ->with(['memberships' => fn ($query) => $query->where('organization_id', $organization->id)->with('role')])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return ApiResponse::paginated(
            $users,
            UserResource::collection($users)->resolve(),
        );
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): JsonResponse
    {
        $user = $action->handle($this->requireOrganization(), $request->validated(), $request->user());

        return ApiResponse::success(
            (new UserResource($user->load('memberships.role')))->resolve(),
            'User created.',
            status: 201,
        );
    }

    public function show(User $user): JsonResponse
    {
        $member = $this->member($user);
        $this->authorize('view', $member);

        return ApiResponse::success((new UserResource($member->load('memberships.role')))->resolve());
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
