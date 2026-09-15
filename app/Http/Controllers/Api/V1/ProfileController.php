<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Support\ApiResponse;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenantContext): JsonResponse
    {
        $user = $request->user()->load([
            'organizations',
            'memberships.role',
        ]);

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve(),
            'current_organization_id' => $tenantContext->id(),
            'role' => $user->roleIn($tenantContext->organization())?->name->value,
            'is_super_admin' => $user->isSuperAdmin(),
        ]);
    }
}
