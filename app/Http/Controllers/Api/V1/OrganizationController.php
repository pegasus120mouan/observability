<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateOrganizationAction;
use App\Actions\UpdateOrganizationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Http\Resources\Api\V1\OrganizationResource;
use App\Models\Organization;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('users')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);

        return ApiResponse::paginated(
            $organizations,
            OrganizationResource::collection($organizations)->resolve(),
        );
    }

    public function store(StoreOrganizationRequest $request, CreateOrganizationAction $action): JsonResponse
    {
        $organization = $action->handle($request->validated(), $request->user());

        return ApiResponse::success(
            (new OrganizationResource($organization))->resolve(),
            'Organization created.',
            status: 201,
        );
    }

    public function show(Organization $organization): JsonResponse
    {
        $this->authorize('view', $organization);

        $organization->loadCount('users');

        return ApiResponse::success((new OrganizationResource($organization))->resolve());
    }

    public function update(
        UpdateOrganizationRequest $request,
        Organization $organization,
        UpdateOrganizationAction $action,
    ): JsonResponse {
        $organization = $action->handle($organization, $request->validated(), $request->user());

        return ApiResponse::success(
            (new OrganizationResource($organization))->resolve(),
            'Organization updated.',
        );
    }
}
