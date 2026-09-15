<?php

namespace App\Http\Middleware;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAgent
{
    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agentUid = $request->header('X-Agent-Id') ?? $request->input('agent_id');
        $apiKey = $request->bearerToken() ?? $request->header('X-Api-Key');

        if (! is_string($agentUid) || $agentUid === '' || ! is_string($apiKey) || $apiKey === '') {
            abort(401, 'Agent credentials are required.');
        }

        $agent = Agent::query()
            ->withoutGlobalScopes()
            ->with('organization')
            ->where('agent_uid', $agentUid)
            ->first();

        if ($agent === null || $agent->isRevoked() || ! $agent->apiKeyMatches($apiKey)) {
            abort(401, 'Invalid agent credentials.');
        }

        if ($agent->organization === null || $agent->organization->isSuspended()) {
            abort(403, 'This organization is suspended.');
        }

        if ($agent->status === AgentStatus::Pending) {
            $agent->forceFill(['status' => AgentStatus::Online])->save();
        }

        $this->tenantContext->set($agent->organization);
        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
