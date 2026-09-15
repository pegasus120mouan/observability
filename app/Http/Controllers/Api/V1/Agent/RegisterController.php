<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Actions\RegisterAgentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAgentRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterAgentRequest $request, RegisterAgentAction $action): JsonResponse
    {
        $result = $action->handle($request->validated());

        return ApiResponse::success([
            'agent_id' => $result['agent']->agent_uid,
            'api_key' => $result['api_key'],
            'host_id' => $result['host']->id,
            'hostname' => $result['host']->hostname,
            'config' => [
                'heartbeat_interval' => (int) config('platform.agents.heartbeat_interval_seconds'),
                'offline_after_minutes' => (int) config('platform.agents.offline_after_minutes'),
            ],
        ], 'Agent registered. Store the API key now; it will not be shown again.', status: 201);
    }
}
