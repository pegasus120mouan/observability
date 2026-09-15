<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');
        $agent->loadMissing('host');

        return ApiResponse::success([
            'agent_id' => $agent->agent_uid,
            'heartbeat_interval' => (int) config('platform.agents.heartbeat_interval_seconds'),
            'offline_after_minutes' => (int) config('platform.agents.offline_after_minutes'),
            'collectors' => [
                'cpu' => true,
                'memory' => true,
                'disk' => true,
                'network' => true,
                'load' => true,
                'uptime' => true,
                'processes' => true,
                'services' => true,
                'logs' => true,
                'apm' => true,
                'http_requests' => true,
            ],
            'host' => [
                'id' => $agent->host?->id,
                'hostname' => $agent->host?->hostname,
                'status' => $agent->host?->status->value,
            ],
        ]);
    }
}
