<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuthTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuditLogger;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthTokenController extends Controller
{
    public function store(StoreAuthTokenRequest $request, AuditLogger $auditLogger): JsonResponse
    {
        $user = $request->userFromCredentials();

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken($request->string('device_name')->toString() ?: 'api')->plainTextToken;

        $auditLogger->log(AuditAction::Login, actor: $user);

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => (new UserResource($user))->resolve(),
        ], 'Authenticated.');
    }

    public function destroy(Request $request, AuditLogger $auditLogger): JsonResponse
    {
        $auditLogger->log(AuditAction::Logout, actor: $request->user());

        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out.');
    }
}
