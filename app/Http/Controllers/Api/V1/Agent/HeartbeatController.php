<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Enums\AgentStatus;
use App\Enums\HostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AgentHeartbeatRequest;
use App\Models\Agent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class HeartbeatController extends Controller
{
    public function __invoke(AgentHeartbeatRequest $request): JsonResponse
    {
        /** @var Agent $agent */
        $agent = $request->attributes->get('agent');
        $agent->loadMissing('host');

        $now = now();

        $agent->forceFill([
            'last_seen_at' => $now,
            'status' => AgentStatus::Online,
            'version' => $request->validated('version') ?? $agent->version,
        ])->save();

        $host = $agent->host;

        if ($host !== null && $host->status !== HostStatus::Maintenance) {
            $host->forceFill([
                'last_seen_at' => $now,
                'status' => HostStatus::Online,
                'ip_address' => $request->validated('ip_address') ?? $host->ip_address ?? $request->ip(),
            ])->save();
        }

        return ApiResponse::success([
            'agent_id' => $agent->agent_uid,
            'status' => $agent->status->value,
            'host_status' => $host?->status->value,
            'server_time' => $now->toIso8601String(),
        ], 'Heartbeat accepted.');
    }
}
